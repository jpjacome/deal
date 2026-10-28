<?php

namespace App\Livewire;

use App\Actions\Bookings\RescheduleBooking;
use App\Exceptions\BookingUnavailable;
use App\Livewire\Concerns\PicksSchedule;
use App\Models\Booking;
use App\Models\Space;
use DomainException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Customer moves their own booking to another day/time of the same space.
 */
class RescheduleForm extends Component
{
    use PicksSchedule;

    #[Locked]
    public string $token;

    public function mount(string $token): void
    {
        $this->token = $token;

        abort_unless($this->booking()->canBeRescheduledByCustomer(), 403);

        $this->resetSchedule();
        $this->attendees = $this->booking()->attendees;
        $this->duration = $this->scheduleSpace()->booking_mode->isFullDay()
            ? null
            : (int) $this->booking()->starts_at->diffInMinutes($this->booking()->ends_at);
    }

    public function booking(): Booking
    {
        return Booking::with(['space', 'customer'])->where('manage_token', $this->token)->firstOrFail();
    }

    protected function scheduleSpace(): ?Space
    {
        return $this->booking()->space;
    }

    protected function ignoredBooking(): ?Booking
    {
        return $this->booking();
    }

    public function save(RescheduleBooking $reschedule)
    {
        if ($this->selection === null) {
            $this->addError('time', 'Elige un horario disponible.');

            return null;
        }

        [$start, $end] = $this->selection;

        try {
            $reschedule->handle($this->booking(), $start, $end, byCustomer: true);
        } catch (BookingUnavailable|DomainException $e) {
            $this->refreshSchedule();
            $this->time = null;
            $this->addError('time', $e->getMessage());

            return null;
        }

        session()->flash('rescheduled', true);

        return $this->redirectRoute('bookings.show', $this->token);
    }

    public function render(): View
    {
        return view('livewire.reschedule-form');
    }
}
