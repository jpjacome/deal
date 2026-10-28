<?php

namespace App\Notifications;

use App\Notifications\WhatsApp\WhatsAppMessage;

/**
 * To the customer, before the visit. On WhatsApp it carries "Confirmo / Cancelar" buttons
 * that come back through the webhook.
 */
class BookingReminder extends BookingNotification
{
    protected function subject(): string
    {
        return "Recordatorio: tu reserva {$this->booking->code}";
    }

    protected function heading(): string
    {
        return 'Te esperamos pronto';
    }

    protected function lines(): array
    {
        return ["Hola {$this->booking->customer->name}, te recordamos tu reserva. Si ya no puedes venir, cancélala para liberar el espacio."];
    }

    protected function action(): ?array
    {
        return ['Ver o cancelar mi reserva', $this->booking->manageUrl()];
    }

    protected function showsLocation(): bool
    {
        return true;
    }

    protected function whatsappMessage(): ?WhatsAppMessage
    {
        return $this->template('recordatorio_reserva', $this->basics(), [
            "CONFIRM:{$this->booking->manage_token}",
            "CANCEL:{$this->booking->manage_token}",
        ]);
    }
}
