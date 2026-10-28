<?php

namespace App\Notifications;

use App\Notifications\WhatsApp\WhatsAppMessage;

/**
 * To the customer: their pending request was not reviewed in time and released the slot.
 */
class BookingExpired extends BookingNotification
{
    protected function subject(): string
    {
        return "Tu solicitud {$this->booking->code} expiró";
    }

    protected function heading(): string
    {
        return 'Tu solicitud expiró';
    }

    protected function lines(): array
    {
        return [
            "Hola {$this->booking->customer->name}, no alcanzamos a confirmar tu solicitud a tiempo y el horario quedó libre. Disculpa las molestias.",
            'Si todavía lo necesitas, puedes volver a reservar en un minuto.',
        ];
    }

    protected function action(): ?array
    {
        return ['Elegir otro horario', $this->bookUrl()];
    }

    protected function whatsappMessage(): ?WhatsAppMessage
    {
        return $this->template('reserva_expirada', [...$this->basics(), $this->bookUrl()]);
    }

    private function bookUrl(): string
    {
        return route('bookings.create', ['espacio' => $this->booking->space->slug]);
    }
}
