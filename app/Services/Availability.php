<?php

namespace App\Services;

use App\Enums\BookingMode;
use App\Exceptions\BookingUnavailable;
use App\Models\BlockedPeriod;
use App\Models\Booking;
use App\Models\OpeningHour;
use App\Models\Space;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Support\Collection;

/**
 * Decides whether a space can be booked for a given time range, and which times are free.
 *
 * All times are in the app timezone (America/Guayaquil, no DST).
 */
class Availability
{
    public const DAY_CLOSED = 'closed';

    public const DAY_FULL = 'full';

    public const DAY_FEW = 'few';

    public const DAY_AVAILABLE = 'available';

    /** @var Collection<int, OpeningHour>|null */
    private ?Collection $openingHours = null;

    /**
     * Opening ranges of a space on a date, earliest first.
     *
     * A space with its own opening hours ignores the venue-wide hours.
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public function openingRanges(Space $space, CarbonImmutable $date): array
    {
        $this->openingHours ??= OpeningHour::orderBy('opens_at')->get();

        $hasOwnHours = $this->openingHours->contains('space_id', $space->id);
        $day = $date->startOfDay();

        return $this->openingHours
            ->where('space_id', $hasOwnHours ? $space->id : null)
            ->where('weekday', $day->dayOfWeekIso)
            ->map(fn (OpeningHour $hours) => [
                $day->setTimeFromTimeString($hours->opens_at),
                $day->setTimeFromTimeString($hours->closes_at),
            ])
            ->values()
            ->all();
    }

    /**
     * The bookable time range of a full-day booking: first opening to last closing.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null Null when closed that day.
     */
    public function dayBounds(Space $space, CarbonImmutable $date): ?array
    {
        $ranges = $this->openingRanges($space, $date);

        return $ranges === [] ? null : [$ranges[0][0], end($ranges)[1]];
    }

    /**
     * Morning and afternoon halves of the day. Uses the venue's own split when it has two ranges
     * (e.g. 08:00–13:00 / 14:00–19:00); otherwise splits at the hour nearest the middle.
     *
     * @return array{am: array{0: CarbonImmutable, 1: CarbonImmutable}, pm: array{0: CarbonImmutable, 1: CarbonImmutable}}|null
     */
    public function halfDayBounds(Space $space, CarbonImmutable $date): ?array
    {
        $ranges = $this->openingRanges($space, $date);

        if ($ranges === []) {
            return null;
        }

        if (count($ranges) >= 2) {
            return ['am' => $ranges[0], 'pm' => end($ranges)];
        }

        [$opens, $closes] = $ranges[0];
        $middle = $opens->addMinutes(intdiv((int) $opens->diffInMinutes($closes), 2))->startOfHour();

        return ['am' => [$opens, $middle], 'pm' => [$middle, $closes]];
    }

    /**
     * Throw when the booking can't be made. Run inside the booking transaction.
     *
     * @param  bool  $strict  Customer rules: lead time, horizon, slot grid and duration limits (staff bookings skip them).
     *
     * @throws BookingUnavailable
     */
    public function ensureBookable(
        Space $space,
        CarbonImmutable $start,
        CarbonImmutable $end,
        int $attendees = 1,
        bool $strict = true,
        ?Booking $ignore = null,
    ): void {
        [$bookings, $blocks] = $this->busyPeriods($space, $start, $end, $ignore);

        $problem = $this->problem($space, $start, $end, $attendees, $strict, $this->openingRanges($space, $start), $bookings, $blocks);

        if ($problem !== null) {
            throw new BookingUnavailable($problem);
        }
    }

    /**
     * Why the booking can't be made, or null when it can.
     */
    public function check(Space $space, CarbonImmutable $start, CarbonImmutable $end, int $attendees = 1, ?Booking $ignore = null): ?string
    {
        try {
            $this->ensureBookable($space, $start, $end, $attendees, ignore: $ignore);
        } catch (BookingUnavailable $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * Every start time on the slot grid for an hourly booking, with whether it's free and why not.
     *
     * @return list<array{start: CarbonImmutable, available: bool, reason: ?string}>
     */
    public function slots(Space $space, CarbonImmutable $date, int $durationMinutes, int $attendees = 1, ?Booking $ignore = null, bool $strict = true): array
    {
        $ranges = $this->openingRanges($space, $date);

        if ($ranges === []) {
            return [];
        }

        [$bookings, $blocks] = $this->busyPeriods($space, $ranges[0][0], end($ranges)[1], $ignore);

        $slots = [];

        foreach ($ranges as [$opens, $closes]) {
            for ($start = $opens; $start->addMinutes($durationMinutes) <= $closes; $start = $start->addMinutes($space->slot_minutes)) {
                $reason = $this->problem($space, $start, $start->addMinutes($durationMinutes), $attendees, $strict, $ranges, $bookings, $blocks);
                $slots[] = ['start' => $start, 'available' => $reason === null, 'reason' => $reason];
            }
        }

        return $slots;
    }

    /**
     * Free start times on a date for an hourly booking of the given length.
     *
     * @return list<CarbonImmutable>
     */
    public function availableStartTimes(Space $space, CarbonImmutable $date, int $durationMinutes, int $attendees = 1, ?Booking $ignore = null): array
    {
        return collect($this->slots($space, $date, $durationMinutes, $attendees, $ignore))
            ->where('available', true)
            ->pluck('start')
            ->all();
    }

    /**
     * Whether a full-day space (daily or capacity mode) still has room on a date.
     */
    public function isDayAvailable(Space $space, CarbonImmutable $date, int $attendees = 1): bool
    {
        $bounds = $this->dayBounds($space, $date);

        return $bounds !== null && $this->check($space, $bounds[0], $bounds[1], $attendees) === null;
    }

    /**
     * Availability of each day in a range, for the date picker strip.
     *
     * @return array<string, string> Y-m-d => one of the DAY_* constants
     */
    public function dayStatuses(Space $space, CarbonImmutable $from, int $days, ?int $durationMinutes = null, int $attendees = 1, ?Booking $ignore = null): array
    {
        $from = $from->startOfDay();
        [$bookings, $blocks] = $this->busyPeriods($space, $from, $from->addDays($days), $ignore);

        $statuses = [];

        for ($i = 0; $i < $days; $i++) {
            $day = $from->addDays($i);
            $ranges = $this->openingRanges($space, $day);
            $free = $ranges === [] ? null : $this->countFreeOptions($space, $day, $ranges, $durationMinutes, $attendees, $bookings, $blocks);

            $statuses[$day->toDateString()] = match (true) {
                $free === null => self::DAY_CLOSED,
                $free === 0 => self::DAY_FULL,
                $free <= 2 => self::DAY_FEW,
                default => self::DAY_AVAILABLE,
            };
        }

        return $statuses;
    }

    /**
     * Other bookable spaces of the same kind that are free for the same time.
     *
     * @return Collection<int, Space>
     */
    public function alternativeSpaces(Space $space, CarbonImmutable $start, CarbonImmutable $end, int $attendees = 1): Collection
    {
        return Space::bookable()
            ->whereKeyNot($space->id)
            ->where('booking_mode', $space->booking_mode)
            ->where('capacity', '>=', $attendees)
            ->orderBy('sort')
            ->get()
            ->filter(function (Space $other) use ($start, $end, $attendees) {
                if ($other->booking_mode->isFullDay()) {
                    return $this->isDayAvailable($other, $start, $attendees);
                }

                return $this->check($other, $start, $end, $attendees) === null;
            })
            ->values();
    }

    /**
     * How many bookable options a day has: free start times (hourly), or free full/half days and seats.
     *
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $ranges
     * @param  Collection<int, Booking>  $bookings
     * @param  Collection<int, BlockedPeriod>  $blocks
     */
    private function countFreeOptions(Space $space, CarbonImmutable $day, array $ranges, ?int $durationMinutes, int $attendees, Collection $bookings, Collection $blocks): int
    {
        if ($space->booking_mode === BookingMode::Hourly) {
            $duration = $durationMinutes ?? $space->min_minutes;
            $count = 0;

            foreach ($ranges as [$opens, $closes]) {
                for ($start = $opens; $start->addMinutes($duration) <= $closes; $start = $start->addMinutes($space->slot_minutes)) {
                    if ($this->problem($space, $start, $start->addMinutes($duration), $attendees, true, $ranges, $bookings, $blocks) === null) {
                        $count++;
                    }
                }
            }

            return $count;
        }

        $options = [[$ranges[0][0], end($ranges)[1]]];

        if ($space->allows_half_day) {
            $halves = $this->halfDayBounds($space, $day);
            $options = [...$options, $halves['am'], $halves['pm']];
        }

        $free = collect($options)->filter(
            fn (array $option) => $this->problem($space, $option[0], $option[1], $attendees, true, $ranges, $bookings, $blocks) === null
        )->count();

        if ($free > 0 && $space->booking_mode === BookingMode::Capacity) {
            // For shared desks, "few" means few seats left.
            $taken = $bookings->filter(fn (Booking $b) => $b->starts_at < $options[0][1] && $b->ends_at > $options[0][0])->sum('attendees');

            return max(0, $space->capacity - $taken);
        }

        return $free === 0 ? 0 : 3;
    }

    /**
     * Bookings holding a slot and blocked periods that could collide with the window.
     *
     * @return array{0: Collection<int, Booking>, 1: Collection<int, BlockedPeriod>}
     */
    private function busyPeriods(Space $space, CarbonImmutable $from, CarbonImmutable $to, ?Booking $ignore = null): array
    {
        $bookings = Booking::whereBelongsTo($space)
            ->holdingSlot()
            ->where('starts_at', '<', $to->addMinutes($space->buffer_minutes))
            ->where('ends_at', '>', $from->subMinutes($space->buffer_minutes))
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->get(['id', 'starts_at', 'ends_at', 'attendees']);

        $blocks = BlockedPeriod::query()
            ->where(fn ($query) => $query->whereNull('space_id')->orWhereBelongsTo($space))
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->get(['id', 'starts_at', 'ends_at']);

        return [$bookings, $blocks];
    }

    /**
     * The first rule the booking breaks, as a user-facing message; null when bookable.
     *
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $ranges
     * @param  Collection<int, Booking>  $bookings
     * @param  Collection<int, BlockedPeriod>  $blocks
     */
    private function problem(
        Space $space,
        CarbonImmutable $start,
        CarbonImmutable $end,
        int $attendees,
        bool $strict,
        array $ranges,
        Collection $bookings,
        Collection $blocks,
    ): ?string {
        if (! $space->is_active || ($strict && ! $space->is_bookable)) {
            return 'Este espacio no está disponible para reservas en línea.';
        }

        if ($end <= $start) {
            return 'El horario seleccionado no es válido.';
        }

        if ($attendees < 1 || $attendees > $space->capacity) {
            return "Este espacio admite hasta {$space->capacity} ".($space->capacity === 1 ? 'persona.' : 'personas.');
        }

        if ($strict) {
            if ($start < now()->toImmutable()->addMinutes($space->lead_time_minutes)) {
                return $start < now()
                    ? 'Ese horario ya pasó.'
                    : 'Las reservas deben hacerse con al menos '.$this->duration($space->lead_time_minutes).' de anticipación.';
            }

            if ($start > today()->toImmutable()->addDays($space->horizon_days)->endOfDay()) {
                return "Solo se puede reservar hasta {$space->horizon_days} días por adelantado.";
            }
        }

        if ($ranges === []) {
            return 'Ese día estamos cerrados.';
        }

        if ($space->booking_mode->isFullDay()) {
            if (! $this->isFullOrHalfDay($space, $start, $end, $ranges)) {
                return $space->allows_half_day
                    ? 'Este espacio se reserva por día completo o medio día.'
                    : 'Este espacio se reserva por día completo.';
            }
        } else {
            $range = collect($ranges)->first(fn ($range) => $start >= $range[0] && $end <= $range[1]);

            if ($range === null) {
                return 'El horario está fuera de nuestro horario de atención.';
            }

            if ($strict) {
                $minutes = (int) $start->diffInMinutes($end);

                if ($minutes < $space->min_minutes || $minutes > $space->max_minutes) {
                    return 'La duración debe estar entre '.$this->duration($space->min_minutes).' y '.$this->duration($space->max_minutes).'.';
                }

                if ((int) $range[0]->diffInMinutes($start) % $space->slot_minutes !== 0) {
                    return 'La hora de inicio no es válida.';
                }
            }
        }

        if ($blocks->contains(fn (BlockedPeriod $block) => $block->starts_at < $end && $block->ends_at > $start)) {
            return 'El espacio no está disponible en ese horario.';
        }

        if ($space->booking_mode === BookingMode::Capacity) {
            $taken = $bookings
                ->filter(fn (Booking $booking) => $booking->starts_at < $end && $booking->ends_at > $start)
                ->sum('attendees');

            if ($taken + $attendees > $space->capacity) {
                $left = max(0, $space->capacity - $taken);

                return $left === 0
                    ? 'No quedan puestos disponibles ese día.'
                    : "Solo quedan {$left} ".($left === 1 ? 'puesto disponible' : 'puestos disponibles').' ese día.';
            }

            return null;
        }

        $buffer = $space->buffer_minutes;

        if ($bookings->contains(fn (Booking $booking) => $booking->starts_at < $end->addMinutes($buffer) && $booking->ends_at > $start->subMinutes($buffer))) {
            return 'Ese horario ya está reservado.';
        }

        return null;
    }

    /**
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $ranges
     */
    private function isFullOrHalfDay(Space $space, CarbonImmutable $start, CarbonImmutable $end, array $ranges): bool
    {
        if ($start->equalTo($ranges[0][0]) && $end->equalTo(end($ranges)[1])) {
            return true;
        }

        if (! $space->allows_half_day) {
            return false;
        }

        $halves = $this->halfDayBounds($space, $start);

        foreach ($halves as [$from, $to]) {
            if ($start->equalTo($from) && $end->equalTo($to)) {
                return true;
            }
        }

        return false;
    }

    private function duration(int $minutes): string
    {
        return CarbonInterval::minutes($minutes)->cascade()->forHumans();
    }
}
