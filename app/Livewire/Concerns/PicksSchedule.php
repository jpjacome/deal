<?php

namespace App\Livewire\Concerns;

use App\Enums\BookingMode;
use App\Models\Booking;
use App\Models\Space;
use App\Services\Availability;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Throwable;

/**
 * Date strip → duration → time (or full/half day) selection, shared by booking and rescheduling.
 *
 * @property-read ?Space $scheduleSpace
 * @property-read ?CarbonImmutable $day
 */
trait PicksSchedule
{
    /** Days shown in the date strip. */
    public const STRIP_DAYS = 14;

    public ?string $date = null;

    public ?int $duration = null;

    public ?string $time = null;

    /** Full-day spaces: full | am | pm */
    public string $period = 'full';

    public int $attendees = 1;

    abstract protected function scheduleSpace(): ?Space;

    /**
     * Booking being moved (its own slot doesn't count as taken).
     */
    protected function ignoredBooking(): ?Booking
    {
        return null;
    }

    public function updatedDate(): void
    {
        $this->time = null;
    }

    public function updatedDuration(): void
    {
        $this->time = null;
    }

    public function updatedAttendees(): void
    {
        $this->time = null;
    }

    public function pickDay(string $date): void
    {
        $this->date = $date;
        $this->time = null;
    }

    protected function resetSchedule(): void
    {
        $space = $this->scheduleSpace();

        $this->date = null;
        $this->time = null;
        $this->period = 'full';
        $this->attendees = 1;
        $this->duration = $space?->booking_mode === BookingMode::Hourly ? $space->min_minutes : null;
    }

    #[Computed]
    public function day(): ?CarbonImmutable
    {
        try {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->date);
        } catch (Throwable) {
            return null;
        }

        return $day && $day->betweenIncluded(today(), $this->maxDate()) ? $day : null;
    }

    public function maxDate(): CarbonImmutable
    {
        return today()->toImmutable()->addDays($this->scheduleSpace()->horizon_days ?? 60);
    }

    /**
     * Duration choices for hourly spaces: whole hours (plus the minimum when it isn't one).
     *
     * @return list<int>
     */
    #[Computed]
    public function durations(): array
    {
        $space = $this->scheduleSpace();

        if ($space?->booking_mode !== BookingMode::Hourly) {
            return [];
        }

        return collect(range($space->min_minutes, $space->max_minutes, $space->slot_minutes))
            ->filter(fn (int $minutes) => $minutes === $space->min_minutes || $minutes % 60 === 0)
            ->values()
            ->all();
    }

    /**
     * Availability of the next days for the strip.
     *
     * @return array<string, string> Y-m-d => Availability::DAY_*
     */
    #[Computed]
    public function dayStatuses(): array
    {
        $space = $this->scheduleSpace();

        if ($space === null) {
            return [];
        }

        $days = min(self::STRIP_DAYS, $space->horizon_days + 1);

        return app(Availability::class)->dayStatuses($space, today()->toImmutable(), $days, $this->duration, max(1, $this->attendees), $this->ignoredBooking());
    }

    #[Computed]
    public function nextAvailableDay(): ?string
    {
        return collect($this->dayStatuses)->search(fn (string $status) => in_array($status, [Availability::DAY_AVAILABLE, Availability::DAY_FEW], true)) ?: null;
    }

    /**
     * Start times for the chosen day and duration, including taken ones (shown disabled).
     * Times inside the minimum notice are left out.
     *
     * @return list<array{start: CarbonImmutable, available: bool, reason: ?string}>
     */
    #[Computed]
    public function timeSlots(): array
    {
        $space = $this->scheduleSpace();

        if ($space?->booking_mode !== BookingMode::Hourly || ! $this->day || ! $this->duration) {
            return [];
        }

        $earliest = now()->addMinutes($space->lead_time_minutes);

        return collect(app(Availability::class)->slots($space, $this->day, $this->duration, max(1, $this->attendees), $this->ignoredBooking()))
            ->filter(fn (array $slot) => $slot['start'] >= $earliest)
            ->values()
            ->all();
    }

    #[Computed]
    public function hasFreeSlot(): bool
    {
        return collect($this->timeSlots)->contains('available', true);
    }

    /**
     * Full-day options (full / am / pm) with whether each is free.
     *
     * @return array<string, array{label: string, range: array{0: CarbonImmutable, 1: CarbonImmutable}, reason: ?string}>
     */
    #[Computed]
    public function dayOptions(): array
    {
        $space = $this->scheduleSpace();

        if (! $space?->booking_mode->isFullDay() || ! $this->day) {
            return [];
        }

        $availability = app(Availability::class);
        $full = $availability->dayBounds($space, $this->day);

        if ($full === null) {
            return [];
        }

        $options = ['full' => ['label' => 'Día completo', 'range' => $full]];

        if ($space->allows_half_day) {
            $halves = $availability->halfDayBounds($space, $this->day);
            $options['am'] = ['label' => 'Mañana', 'range' => $halves['am']];
            $options['pm'] = ['label' => 'Tarde', 'range' => $halves['pm']];
        }

        return collect($options)->map(fn (array $option) => $option + [
            'reason' => $availability->check($space, $option['range'][0], $option['range'][1], max(1, $this->attendees), $this->ignoredBooking()),
        ])->all();
    }

    /**
     * The chosen time range, once everything needed is picked and free.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    #[Computed]
    public function selection(): ?array
    {
        $space = $this->scheduleSpace();

        if (! $space || ! $this->day) {
            return null;
        }

        if ($space->booking_mode->isFullDay()) {
            $option = $this->dayOptions[$this->period] ?? null;

            return $option && $option['reason'] === null ? $option['range'] : null;
        }

        if (! $this->time || ! $this->duration) {
            return null;
        }

        $start = $this->day->setTimeFromTimeString($this->time);

        return [$start, $start->addMinutes($this->duration)];
    }

    /**
     * Other spaces of the same kind free on the chosen day (and time, for hourly spaces), when this one isn't.
     *
     * @return Collection<int, Space>
     */
    #[Computed]
    public function alternatives(): Collection
    {
        $space = $this->scheduleSpace();

        if (! $space || ! $this->day || $this->ignoredBooking()) {
            return collect();
        }

        $availability = app(Availability::class);

        if ($space->booking_mode->isFullDay()) {
            $full = $this->dayOptions['full'] ?? null;

            return $full && $full['reason'] !== null
                ? $availability->alternativeSpaces($space, $full['range'][0], $full['range'][1], max(1, $this->attendees))
                : collect();
        }

        if ($this->hasFreeSlot) {
            return collect();
        }

        return Space::bookable()
            ->whereKeyNot($space->id)
            ->where('booking_mode', BookingMode::Hourly)
            ->where('capacity', '>=', max(1, $this->attendees))
            ->orderBy('sort')
            ->get()
            ->filter(fn (Space $other) => $availability->availableStartTimes($other, $this->day, max($other->min_minutes, $this->duration ?? 0), max(1, $this->attendees)) !== [])
            ->values();
    }

    /**
     * Estimated total for the current selection, or null when the space has no numeric price.
     */
    #[Computed]
    public function estimatedPrice(): ?float
    {
        $space = $this->scheduleSpace();

        if (! $space || ! $this->selection) {
            return null;
        }

        return $space->estimatePrice((int) $this->selection[0]->diffInMinutes($this->selection[1]), max(1, $this->attendees), $this->period !== 'full');
    }

    /**
     * Forget cached availability after a failed attempt (someone else took the slot).
     */
    protected function refreshSchedule(): void
    {
        unset($this->dayStatuses, $this->timeSlots, $this->hasFreeSlot, $this->dayOptions, $this->selection, $this->alternatives, $this->nextAvailableDay);
    }
}
