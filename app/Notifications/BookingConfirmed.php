<?php

namespace App\Notifications;

use App\Models\Setting;
use App\Notifications\WhatsApp\WhatsAppMessage;

/**
 * To the customer: the booking is confirmed (instantly or by Deal). Includes the .ics file.
 */
class BookingConfirmed extends BookingNotification
{
    protected function subject(): string
    {
        return "Reserva confirmada · {$this->booking->code}";
    }

    protected function heading(): string
    {
        return '¡Tu reserva está confirmada!';
    }

    protected function lines(): array
    {
        return ["Hola {$this->booking->customer->name}, te esperamos. Adjuntamos el evento para tu calendario."];
    }

    protected function action(): ?array
    {
        return ['Ver o cancelar mi reserva', $this->booking->manageUrl()];
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
        return $this->template('reserva_confirmada', [
            ...$this->basics(),
            (string) Setting::get('venue_address'),
            $this->booking->manageUrl(),
        ]);
    }
}
