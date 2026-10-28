<?php

namespace App\Notifications;

use App\Notifications\WhatsApp\WhatsAppMessage;

/**
 * To Deal's team: a customer cancelled or rescheduled from their booking page / WhatsApp.
 */
class StaffBookingCancelled extends BookingNotification
{
    protected function subject(): string
    {
        return "Cancelada por el cliente: {$this->booking->space->name} · {$this->booking->scheduleLabel()}";
    }

    protected function heading(): string
    {
        return 'Un cliente canceló su reserva';
    }

    protected function lines(): array
    {
        return ["{$this->booking->customer->name} canceló la reserva {$this->booking->code}. El horario quedó libre."];
    }

    protected function action(): ?array
    {
        return ['Ver en el panel', route('filament.admin.resources.bookings.view', $this->booking)];
    }

    protected function whatsappMessage(): ?WhatsAppMessage
    {
        return $this->template('reserva_cancelada_admin', [
            $this->booking->code,
            $this->booking->space->name,
            $this->booking->scheduleLabel(),
            $this->booking->customer->name,
            route('filament.admin.resources.bookings.view', $this->booking),
        ]);
    }
}
