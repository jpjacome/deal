<?php

namespace App\Notifications;

use App\Enums\BookingStatus;
use App\Notifications\WhatsApp\WhatsAppMessage;

/**
 * To the customer: the booking moved to a new time (by them or by Deal). Includes the updated .ics.
 */
class BookingRescheduled extends BookingNotification
{
    protected function subject(): string
    {
        return "Nuevo horario para tu reserva {$this->booking->code}";
    }

    protected function heading(): string
    {
        return 'Tu reserva tiene un nuevo horario';
    }

    protected function lines(): array
    {
        return array_values(array_filter([
            "Hola {$this->booking->customer->name}, actualizamos tu reserva. Estos son los nuevos datos:",
            $this->booking->status === BookingStatus::Pending ? 'El nuevo horario queda pendiente hasta que lo confirmemos.' : null,
        ]));
    }

    protected function showsLocation(): bool
    {
        return true;
    }

    protected function attachesCalendar(): bool
    {
        return true;
    }

    protected function whatsappMessage(): ?WhatsAppMessage
    {
        return $this->template('reserva_reprogramada', [...$this->basics(), $this->booking->manageUrl()]);
    }
}
