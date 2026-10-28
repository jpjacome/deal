<?php

namespace App\Services;

use App\Enums\BookingMode;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Space;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Figures for the Reportes page over a date range (by booking start).
 */
class BookingReports
{
    /** @var Collection<int, Booking> */
    private Collection $bookings;

    public function __construct(
        private Availability $availability,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {
        $this->bookings = Booking::with('customer:id,name,company')
            ->whereBetween('starts_at', [$from->startOfDay(), $to->endOfDay()])
            ->get();
    }

    public static function lastDays(int $days): self
    {
        return new self(app(Availability::class), today()->toImmutable()->subDays($days - 1), today()->toImmutable());
    }

    /**
     * @return array{total: int, completed: int, upcoming: int, no_show: int, cancelled: int, cancelled_by_customer: int, rejected: int, expired: int, web: int, admin: int}
     */
    public function totals(): array
    {
        $count = fn (BookingStatus $status) => $this->bookings->where('status', $status)->count();

        return [
            'total' => $this->bookings->count(),
            'completed' => $count(BookingStatus::Completed),
            'upcoming' => $this->bookings->whereIn('status', BookingStatus::holdingSlot())->count(),
            'no_show' => $count(BookingStatus::NoShow),
            'cancelled' => $count(BookingStatus::Cancelled),
            'cancelled_by_customer' => $this->bookings->where('status', BookingStatus::Cancelled)->filter(fn (Booking $b) => str_contains((string) $b->status_reason, 'cliente'))->count(),
            'rejected' => $count(BookingStatus::Rejected),
            'expired' => $count(BookingStatus::Expired),
            'web' => $this->bookings->where('source', BookingSource::Web)->count(),
            'admin' => $this->bookings->where('source', BookingSource::Admin)->count(),
        ];
    }

    /**
     * No-shows over visits that should have happened (completed + no-show), as a percentage.
     */
    public function noShowRate(): ?float
    {
        $totals = $this->totals();
        $visits = $totals['completed'] + $totals['no_show'];

        return $visits === 0 ? null : round($totals['no_show'] / $visits * 100, 1);
    }

    public function cancellationRate(): ?float
    {
        $totals = $this->totals();

        return $totals['total'] === 0 ? null : round($totals['cancelled'] / $totals['total'] * 100, 1);
    }

    /**
     * Average hours between booking and visit, for website bookings.
     */
    public function averageLeadHours(): ?float
    {
        $web = $this->bookings->where('source', BookingSource::Web);

        return $web->isEmpty() ? null : round($web->avg(fn (Booking $b) => $b->created_at->diffInMinutes($b->starts_at) / 60), 1);
    }

    /**
     * Share of open time (or seats, for shared desks) that was booked, per space.
     *
     * @return list<array{space: Space, percent: float, booked_hours: float}>
     */
    public function occupancy(): array
    {
        $occupying = [BookingStatus::Confirmed, BookingStatus::Completed, BookingStatus::NoShow, BookingStatus::Pending];

        return Space::where('is_active', true)->orderBy('sort')->get()->map(function (Space $space) use ($occupying) {
            $openMinutes = 0;

            for ($day = $this->from; $day <= $this->to; $day = $day->addDay()) {
                foreach ($this->availability->openingRanges($space, $day) as [$opens, $closes]) {
                    $openMinutes += $opens->diffInMinutes($closes);
                }
            }

            $booked = $this->bookings->where('space_id', $space->id)->whereIn('status', $occupying);
            $bookedMinutes = $booked->sum(fn (Booking $b) => $b->starts_at->diffInMinutes($b->ends_at) * ($space->booking_mode === BookingMode::Capacity ? $b->attendees : 1));
            $capacity = $space->booking_mode === BookingMode::Capacity ? $space->capacity : 1;

            return [
                'space' => $space,
                'percent' => $openMinutes > 0 ? round(min(100, $bookedMinutes / ($openMinutes * $capacity) * 100), 1) : 0.0,
                'booked_hours' => round($bookedMinutes / 60, 1),
            ];
        })->all();
    }

    /**
     * Bookings starting per weekday (1–7) and hour, to spot busy and quiet times.
     *
     * @return array{hours: list<int>, rows: array<int, array<int, int>>, max: int}
     */
    public function heatmap(): array
    {
        $active = $this->bookings->whereNotIn('status', [BookingStatus::Rejected, BookingStatus::Expired, BookingStatus::Cancelled]);
        $hours = $active->isEmpty() ? range(8, 18) : range($active->min(fn (Booking $b) => $b->starts_at->hour), $active->max(fn (Booking $b) => $b->starts_at->hour));

        $rows = [];
        foreach (range(1, 7) as $weekday) {
            foreach ($hours as $hour) {
                $rows[$weekday][$hour] = 0;
            }
        }

        foreach ($active as $booking) {
            $rows[$booking->starts_at->dayOfWeekIso][$booking->starts_at->hour]++;
        }

        return ['hours' => $hours, 'rows' => $rows, 'max' => max(1, ...array_map('max', $rows))];
    }

    /**
     * @return Collection<int, array{name: string, company: ?string, visits: int, no_shows: int}>
     */
    public function topCustomers(int $limit = 10): Collection
    {
        return $this->bookings
            ->whereIn('status', [BookingStatus::Completed, BookingStatus::Confirmed, BookingStatus::NoShow])
            ->groupBy('customer_id')
            ->map(fn (Collection $bookings) => [
                'name' => $bookings->first()->customer->name,
                'company' => $bookings->first()->customer->company,
                'visits' => $bookings->whereIn('status', [BookingStatus::Completed, BookingStatus::Confirmed])->count(),
                'no_shows' => $bookings->where('status', BookingStatus::NoShow)->count(),
            ])
            ->sortByDesc('visits')
            ->take($limit)
            ->values();
    }
}
