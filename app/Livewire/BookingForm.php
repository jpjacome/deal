<?php

namespace App\Livewire;

use App\Actions\Bookings\CreateBooking;
use App\Enums\BookingSource;
use App\Exceptions\BookingUnavailable;
use App\Models\Space;
use App\Services\Availability;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * Public booking flow: space → date → time → contact details.
 */
class BookingForm extends Component
{
    #[Url(as: 'espacio')]
    public ?string $spaceSlug = null;

    public ?string $date = null;

    public ?int $duration = null;

    public ?string $time = null;

    public int $attendees = 1;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $company = '';

    public string $notes = '';

    public bool $privacy = false;

    public bool $whatsapp = true;

    /** Honeypot: hidden from people, bots fill it in. */
    public string $website = '';

    public function mount(): void
    {
        if ($this->space === null) {
            $this->spaceSlug = null;
        }

        $this->resetSchedule();
    }

    public function updatedSpaceSlug(): void
    {
        $this->resetSchedule();
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

    /**
     * @return Collection<int, Space>
     */
    #[Computed]
    public function spaces(): Collection
    {
        return Space::bookable()->orderBy('sort')->orderBy('name')->get();
    }

    #[Computed]
    public function space(): ?Space
    {
        return $this->spaces->firstWhere('slug', $this->spaceSlug);
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

    /**
     * Duration options in minutes for hourly spaces.
     *
     * @return list<int>
     */
    #[Computed]
    public function durations(): array
    {
        $space = $this->space;

        return $space ? range($space->min_minutes, $space->max_minutes, $space->slot_minutes) : [];
    }

    /**
     * @return list<CarbonImmutable>
     */
    #[Computed]
    public function startTimes(): array
    {
        if (! $this->space || ! $this->day || ! $this->duration) {
            return [];
        }

        return app(Availability::class)->availableStartTimes($this->space, $this->day, $this->duration, $this->attendees);
    }

    /**
     * Why the selected full day can't be booked; null when it can (or nothing is selected).
     */
    #[Computed]
    public function dayProblem(): ?string
    {
        if (! $this->space?->booking_mode->isFullDay() || ! $this->day) {
            return null;
        }

        $bounds = app(Availability::class)->dayBounds($this->space, $this->day);

        if ($bounds === null) {
            return 'Ese día estamos cerrados.';
        }

        try {
            app(Availability::class)->ensureBookable($this->space, $bounds[0], $bounds[1], $this->attendees);
        } catch (BookingUnavailable $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * The chosen time range, once space, date and time are all picked.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    #[Computed]
    public function selection(): ?array
    {
        if (! $this->space || ! $this->day) {
            return null;
        }

        if ($this->space->booking_mode->isFullDay()) {
            return $this->dayProblem === null ? app(Availability::class)->dayBounds($this->space, $this->day) : null;
        }

        if (! $this->time || ! $this->duration) {
            return null;
        }

        $start = $this->day->setTimeFromTimeString($this->time);

        return [$start, $start->addMinutes($this->duration)];
    }

    public function maxDate(): CarbonImmutable
    {
        return today()->toImmutable()->addDays($this->space->horizon_days ?? 60);
    }

    public function book(CreateBooking $createBooking)
    {
        if ($this->website !== '') {
            return null;
        }

        $this->phone = PhoneNumber::normalize($this->phone) ?? $this->phone;

        $this->validate([
            'spaceSlug' => ['required'],
            'date' => ['required', 'date_format:Y-m-d'],
            'attendees' => ['required', 'integer', 'min:1', 'max:'.($this->space->capacity ?? 1)],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'regex:/^\+[1-9]\d{7,14}$/'],
            'company' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'privacy' => ['accepted'],
        ], [
            'phone.regex' => 'Ingresa un número de celular válido, por ejemplo 099 123 4567.',
            'privacy.accepted' => 'Debes aceptar la política de privacidad para reservar.',
        ]);

        if ($this->selection === null) {
            $this->addError('time', 'Elige un horario disponible.');

            return null;
        }

        $rateKey = 'booking:'.request()->ip();

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $this->addError('form', 'Has hecho demasiadas reservas seguidas. Inténtalo más tarde o escríbenos por WhatsApp.');

            return null;
        }

        RateLimiter::hit($rateKey, 3600);

        [$start, $end] = $this->selection;

        try {
            $booking = $createBooking->handle(
                $this->space,
                $start,
                $end,
                [
                    'name' => $this->name,
                    'email' => $this->email,
                    'phone' => $this->phone,
                    'company' => $this->company ?: null,
                    'whatsapp_opt_in' => $this->whatsapp,
                ],
                attendees: $this->attendees,
                notes: $this->notes ?: null,
                source: BookingSource::Web,
            );
        } catch (BookingUnavailable $e) {
            // Someone else took the slot meanwhile: refresh the options and explain.
            unset($this->startTimes, $this->dayProblem, $this->selection);
            $this->time = null;
            $this->addError('time', $e->getMessage());

            return null;
        }

        session()->flash('booked', true);

        return $this->redirectRoute('bookings.show', $booking->manage_token);
    }

    private function resetSchedule(): void
    {
        $this->date = null;
        $this->time = null;
        $this->attendees = 1;
        $this->duration = $this->space?->booking_mode->isFullDay() ? null : $this->space?->min_minutes;
    }

    public function render(): View
    {
        return view('livewire.booking-form');
    }
}
