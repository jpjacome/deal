<?php

namespace App\Livewire;

use App\Actions\Bookings\CreateBooking;
use App\Enums\BookingSource;
use App\Exceptions\BookingUnavailable;
use App\Livewire\Concerns\PicksSchedule;
use App\Models\Space;
use App\Models\WaitlistEntry;
use App\Support\PhoneNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Public booking flow: space → day → time → contact details.
 */
class BookingForm extends Component
{
    use PicksSchedule;

    #[Url(as: 'espacio')]
    public ?string $spaceSlug = null;

    /** Pre-selected day/time from links ("se liberó un espacio", "libre ahora"). */
    #[Url(as: 'fecha', except: null)]
    public ?string $initialDate = null;

    #[Url(as: 'hora', except: null)]
    public ?string $initialTime = null;

    public string $name = '';

    public string $email = '';

    public string $countryCode = '593';

    public string $phone = '';

    public string $company = '';

    public string $notes = '';

    public bool $privacy = false;

    /** Explicit opt-in (LOPDP / Meta policy): never pre-ticked. */
    public bool $whatsapp = false;

    /** Honeypot: hidden from people, bots fill it in. */
    public string $website = '';

    public bool $joinedWaitlist = false;

    public function mount(): void
    {
        if ($this->space === null) {
            $this->spaceSlug = null;
        }

        $this->resetSchedule();

        if ($this->space && $this->initialDate) {
            $this->date = $this->initialDate;
            $this->time = $this->initialTime;

            if (! $this->day) {
                $this->date = $this->time = null;
            }
        }
    }

    public function updatedSpaceSlug(): void
    {
        $this->resetSchedule();
        $this->initialDate = $this->initialTime = null;
        $this->joinedWaitlist = false;
    }

    public function switchSpace(string $slug): void
    {
        $date = $this->date;
        $this->spaceSlug = $slug;
        $this->updatedSpaceSlug();
        $this->date = $date;
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

    protected function scheduleSpace(): ?Space
    {
        return $this->space;
    }

    public function book(CreateBooking $createBooking)
    {
        if ($this->website !== '') {
            return null;
        }

        $this->phone = PhoneNumber::normalize($this->phone, $this->countryCode) ?? $this->phone;
        $this->email = Str::lower(trim($this->email));

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
            'phone.regex' => 'Revisa el número de celular y el código de país.',
            'privacy.accepted' => 'Debes aceptar la política de privacidad y las condiciones para reservar.',
        ]);

        if ($this->selection === null) {
            $this->addError('time', 'Elige un horario disponible.');

            return null;
        }

        $rateKey = 'booking:'.request()->ip();

        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
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
            $this->refreshSchedule();
            $this->time = null;
            $this->addError('time', $e->getMessage());

            return null;
        }

        session()->flash('booked', true);

        return $this->redirectRoute('bookings.show', $booking->manage_token);
    }

    /**
     * "Avísame si se libera": join the waitlist for the chosen space and day.
     */
    public function joinWaitlist(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
        ]);

        if (! $this->space || ! $this->day) {
            return;
        }

        WaitlistEntry::firstOrCreate(
            ['space_id' => $this->space->id, 'date' => $this->day->toDateString(), 'email' => Str::lower(trim($this->email)), 'notified_at' => null],
            ['name' => $this->name, 'phone' => PhoneNumber::normalize($this->phone, $this->countryCode), 'whatsapp_opt_in' => $this->whatsapp],
        );

        $this->joinedWaitlist = true;
    }

    public function render(): View
    {
        return view('livewire.booking-form', ['countries' => PhoneNumber::COUNTRIES]);
    }
}
