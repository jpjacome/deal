<?php

namespace App\Notifications;

use App\Models\Setting;
use App\Notifications\WhatsApp\WhatsAppMessage;

/**
 * To the customer: their request is waiting for Deal's confirmation.
 */
class BookingRequested extends BookingNotification
{
    protected function subject(): string
    {
        return "Recibimos tu solicitud de reserva {$this->booking->code}";
    }

    protected function heading(): string
    {
        return '¡Recibimos tu solicitud!';
    }

    protected function lines(): array
    {
        $hours = (int) Setting::get('pending_expiry_hours');

        return [
            "Hola {$this->booking->customer->name}, revisaremos tu reserva y te avisaremos en cuanto esté confirmada"
                .($hours > 0 ? " (como máximo en {$hours} horas)." : '.'),
        ];
    }

    protected function whatsappMessage(): ?WhatsAppMessage
    {
        return $this->template('reserva_recibida', [...$this->basics(), $this->booking->manageUrl()]);
    }
}
