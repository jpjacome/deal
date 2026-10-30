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
 * Figures for the Reportes page. Three views of the same period:
 * - received: bookings made in the period (by created_at) — updates the moment someone books;
 * - visits: bookings whose date fell in the period (by starts_at, up to now) — what actually happened;
 * - upcoming: active bookings for the next days — what's coming.
 */
class BookingReports
{
    public const UPCOMING_DAYS = 30;

    /** @var Collection<int, Booking> */
    private Collection $received;

    /** @var Collection<int, Booking> */
    private Collection $visits;

    /** @var Collection<int, Booking> */
    private Collection $upcoming;

    public function __construct(
        private Availability $availability,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {
        $now = now()->toImmutable();

        $this->received = Booking::with('customer:id,name,company')
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->get();

        $this->visits = Booking::query()
            ->whereBetween('starts_at', [$from->startOfDay(), min($now, $to->endOfDay())])
            ->get();

        $this->upcoming = Booking::query()
            ->holdingSlot()
            ->whereBetween('starts_at', [$now, $now->addDays(self::UPCOMING_DAYS)->endOfDay()])
            ->get();
    }

    public static function lastDays(int $days): self
    {
        return new self(app(Availability::class), today()->toImmutable()->subDays($days - 1), today()->toImmutable());
    }

    /**
     * @return array{received: int, web: int, admin: int, cancelled: int, cancelled_by_customer: int, rejected: int, expired: int, upcoming: int, upcoming_pending: int, completed: int, no_show: int}
     */
    public function totals(): array
    {
        $received = fn (BookingStatus $status) => $this->received->where('status', $status)->count();

        return [
            'received' => $this->received->count(),
            'web' => $this->received->where('source', BookingSource::Web)->count(),
            'admin' => $this->received->where('source', BookingSource::Admin)->count(),
            'cancelled' => $received(BookingStatus::Cancelled),
            'cancelled_by_customer' => $this->received->where('status', BookingStatus::Cancelled)->filter(fn (Booking $b) => str_contains((string) $b->status_reason, 'cliente'))->count(),
            'rejected' => $received(BookingStatus::Rejected),
            'expired' => $received(BookingStatus::Expired),
            'upcoming' => $this->upcoming->count(),
            'upcoming_pending' => $this->upcoming->where('status', BookingStatus::Pending)->count(),
            'completed' => $this->visits->where('status', BookingStatus::Completed)->count(),
            'no_show' => $this->visits->where('status', BookingStatus::NoShow)->count(),
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

    /**
     * Cancellations among the bookings received in the period, as a percentage.
     */
    public function cancellationRate(): ?float
    {
        $totals = $this->totals();

        return $totals['received'] === 0 ? null : round($totals['cancelled'] / $totals['received'] * 100, 1);
    }

    /**
     * Average hours between booking and visit, for website bookings received in the period.
     */
    public function averageLeadHours(): ?float
    {
        $web = $this->received->where('source', BookingSource::Web);

        return $web->isEmpty() ? null : round($web->avg(fn (Booking $b) => $b->created_at->diffInMinutes($b->starts_at) / 60), 1);
    }

    /**
     * Share of open time (or seats, for shared desks) booked per space: in the period so far, and for the next days.
     *
     * @return list<array{space: Space, past_percent: float, past_hours: float, upcoming_percent: float, upcoming_hours: float}>
     */
    public function occupancy(): array
    {
        $now = now()->toImmutable();
        $occupying = [BookingStatus::Confirmed, BookingStatus::Completed, BookingStatus::NoShow, BookingStatus::Pending];

        return Space::where('is_active', true)->orderBy('sort')->get()->map(function (Space $space) use ($now, $occupying) {
            [$pastPercent, $pastHours] = $this->occupancyFor($space, $this->visits->whereIn('status', $occupying), $this->from->startOfDay(), min($now, $this->to->endOfDay()));
            [$upcomingPercent, $upcomingHours] = $this->occupancyFor($space, $this->upcoming, $now, $now->addDays(self::UPCOMING_DAYS)->endOfDay());

            return [
                'space' => $space,
                'past_percent' => $pastPercent,
                'past_hours' => $pastHours,
                'upcoming_percent' => $upcomingPercent,
                'upcoming_hours' => $upcomingHours,
            ];
        })->all();
    }

    /**
     * Demand by weekday (1–7) and start hour, from the bookings received in the period.
     *
     * @return array{hours: list<int>, rows: array<int, array<int, int>>, max: int}
     */
    public function heatmap(): array
    {
        $active = $this->received->whereNotIn('status', [BookingStatus::Rejected, BookingStatus::Expired, BookingStatus::Cancelled]);
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
     * @return Collection<int, array{name: string, company: ?string, bookings: int, no_shows: int}>
     */
    public function topCustomers(int $limit = 10): Collection
    {
        return $this->received
            ->whereNotIn('status', [BookingStatus::Rejected, BookingStatus::Expired])
            ->groupBy('customer_id')
            ->map(fn (Collection $bookings) => [
                'name' => $bookings->first()->customer->name,
                'company' => $bookings->first()->customer->company,
                'bookings' => $bookings->count(),
                'no_shows' => $bookings->where('status', BookingStatus::NoShow)->count(),
            ])
            ->sortByDesc('bookings')
            ->take($limit)
            ->values();
    }

    /**
     * @param  Collection<int, Booking>  $bookings
     * @return array{0: float, 1: float} [percent, booked hours]
     */
    private function occupancyFor(Space $space, Collection $bookings, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $openMinutes = 0;

        for ($day = $from->startOfDay(); $day <= $to; $day = $day->addDay()) {
            foreach ($this->availability->openingRanges($space, $day) as [$opens, $closes]) {
                $start = max($opens, $from);
                $end = min($closes, $to);
                $openMinutes += max(0, (int) $start->diffInMinutes($end, false));
            }
        }

        $isCapacity = $space->booking_mode === BookingMode::Capacity;
        $bookedMinutes = $bookings
            ->where('space_id', $space->id)
            ->sum(fn (Booking $b) => $b->starts_at->diffInMinutes($b->ends_at) * ($isCapacity ? $b->attendees : 1));
        $capacity = $isCapacity ? $space->capacity : 1;

        return [
            $openMinutes > 0 ? round(min(100, $bookedMinutes / ($openMinutes * $capacity) * 100), 1) : 0.0,
            round($bookedMinutes / 60, 1),
        ];
    }
}
