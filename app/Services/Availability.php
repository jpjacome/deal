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
 * Decides whether a space can be booked for a given time range, and which start times are free.
 *
 * All times are in the app timezone (America/Guayaquil, no DST).
 */
class Availability
{
    /**
     * Opening ranges of a space on a date, earliest first.
     *
     * A space with its own opening hours ignores the venue-wide hours.
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public function openingRanges(Space $space, CarbonImmutable $date): array
    {
        $day = $date->startOfDay();
        $hasOwnHours = OpeningHour::whereBelongsTo($space)->exists();

        return OpeningHour::query()
            ->when($hasOwnHours, fn ($query) => $query->whereBelongsTo($space), fn ($query) => $query->whereNull('space_id'))
            ->where('weekday', $day->dayOfWeekIso)
            ->orderBy('opens_at')
            ->get()
            ->map(fn (OpeningHour $hours) => [
                $day->setTimeFromTimeString($hours->opens_at),
                $day->setTimeFromTimeString($hours->closes_at),
            ])
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
     * Throw when the booking can't be made. Run inside the booking transaction.
     *
     * @param  bool  $enforceWindow  Apply lead time and booking horizon (staff bookings skip them).
     *
     * @throws BookingUnavailable
     */
    public function ensureBookable(
        Space $space,
        CarbonImmutable $start,
        CarbonImmutable $end,
        int $attendees = 1,
        bool $enforceWindow = true,
        ?Booking $ignore = null,
    ): void {
        $ranges = $this->openingRanges($space, $start);
        [$bookings, $blocks] = $this->busyPeriods($space, $start, $end, $ignore);

        $problem = $this->problem($space, $start, $end, $attendees, $enforceWindow, $ranges, $bookings, $blocks);

        if ($problem !== null) {
            throw new BookingUnavailable($problem);
        }
    }

    /**
     * Free start times on a date for an hourly booking of the given length.
     *
     * @return list<CarbonImmutable>
     */
    public function availableStartTimes(Space $space, CarbonImmutable $date, int $durationMinutes, int $attendees = 1): array
    {
        $ranges = $this->openingRanges($space, $date);

        if ($ranges === []) {
            return [];
        }

        [$bookings, $blocks] = $this->busyPeriods($space, $ranges[0][0], end($ranges)[1]);

        $starts = [];

        foreach ($ranges as [$opens, $closes]) {
            for ($start = $opens; $start->addMinutes($durationMinutes) <= $closes; $start = $start->addMinutes($space->slot_minutes)) {
                $end = $start->addMinutes($durationMinutes);

                if ($this->problem($space, $start, $end, $attendees, true, $ranges, $bookings, $blocks) === null) {
                    $starts[] = $start;
                }
            }
        }

        return $starts;
    }

    /**
     * Whether a full-day space (daily or capacity mode) still has room on a date.
     */
    public function isDayAvailable(Space $space, CarbonImmutable $date, int $attendees = 1): bool
    {
        $bounds = $this->dayBounds($space, $date);

        if ($bounds === null) {
            return false;
        }

        try {
            $this->ensureBookable($space, $bounds[0], $bounds[1], $attendees);

            return true;
        } catch (BookingUnavailable) {
            return false;
        }
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
        bool $enforceWindow,
        array $ranges,
        Collection $bookings,
        Collection $blocks,
    ): ?string {
        if (! $space->is_active || ! $space->is_bookable) {
            return 'Este espacio no está disponible para reservas en línea.';
        }

        if ($end <= $start) {
            return 'El horario seleccionado no es válido.';
        }

        if ($attendees < 1 || $attendees > $space->capacity) {
            return "Este espacio admite hasta {$space->capacity} ".($space->capacity === 1 ? 'persona.' : 'personas.');
        }

        if ($enforceWindow) {
            if ($start < now()->toImmutable()->addMinutes($space->lead_time_minutes)) {
                $notice = CarbonInterval::minutes($space->lead_time_minutes)->cascade()->forHumans();

                return "Las reservas deben hacerse con al menos {$notice} de anticipación.";
            }

            if ($start > today()->toImmutable()->addDays($space->horizon_days)->endOfDay()) {
                return "Solo se puede reservar hasta {$space->horizon_days} días por adelantado.";
            }
        }

        if ($ranges === []) {
            return 'Ese día estamos cerrados.';
        }

        if ($space->booking_mode->isFullDay()) {
            if (! $start->equalTo($ranges[0][0]) || ! $end->equalTo(end($ranges)[1])) {
                return 'Este espacio se reserva por día completo.';
            }
        } else {
            $minutes = (int) $start->diffInMinutes($end);

            if ($minutes < $space->min_minutes || $minutes > $space->max_minutes) {
                return 'La duración debe estar entre '.$this->duration($space->min_minutes).' y '.$this->duration($space->max_minutes).'.';
            }

            $range = collect($ranges)->first(fn ($range) => $start >= $range[0] && $end <= $range[1]);

            if ($range === null) {
                return 'El horario está fuera de nuestro horario de atención.';
            }

            if ((int) $range[0]->diffInMinutes($start) % $space->slot_minutes !== 0) {
                return 'La hora de inicio no es válida.';
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

    private function duration(int $minutes): string
    {
        return CarbonInterval::minutes($minutes)->cascade()->forHumans();
    }
}
