<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\WhatsApp\WhatsAppMessage;

/**
 * To the customer: the booking was cancelled, by them or by Deal.
 */
class BookingCancelled extends BookingNotification
{
    public function __construct(Booking $booking, public bool $byCustomer = false)
    {
        parent::__construct($booking);
    }

    protected function subject(): string
    {
        return "Reserva cancelada · {$this->booking->code}";
    }

    protected function heading(): string
    {
        return 'Tu reserva fue cancelada';
    }

    protected function lines(): array
    {
        return array_values(array_filter([
            $this->byCustomer
                ? "Hola {$this->booking->customer->name}, confirmamos que cancelaste tu reserva."
                : "Hola {$this->booking->customer->name}, tuvimos que cancelar tu reserva. Disculpa las molestias.",
            ! $this->byCustomer && $this->booking->status_reason ? "Motivo: {$this->booking->status_reason}" : null,
        ]));
    }

    protected function action(): ?array
    {
        return ['Reservar otro horario', route('bookings.create', ['espacio' => $this->booking->space->slug])];
    }

    protected function whatsappMessage(): ?WhatsAppMessage
    {
        $note = $this->byCustomer
            ? 'Cancelaste tu reserva.'
            : 'Motivo: '.($this->booking->status_reason ?: 'no indicado').'.';

        return $this->template('reserva_cancelada', [...$this->basics(), $note]);
    }
}
