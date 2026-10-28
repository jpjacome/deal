<?php

namespace App\Notifications;

use App\Enums\BookingStatus;
use App\Notifications\WhatsApp\WhatsAppMessage;

/**
 * To Deal's team: a customer booked on the website.
 */
class StaffNewBooking extends BookingNotification
{
    protected function subject(): string
    {
        $prefix = $this->booking->status === BookingStatus::Pending ? 'Por confirmar' : 'Nueva reserva';

        return "{$prefix}: {$this->booking->space->name} · {$this->booking->scheduleLabel()}";
    }

    protected function heading(): string
    {
        return $this->booking->status === BookingStatus::Pending ? 'Nueva reserva por confirmar' : 'Nueva reserva confirmada';
    }

    protected function lines(): array
    {
        $customer = $this->booking->customer;

        return array_values(array_filter([
            "Cliente: {$customer->name} · {$customer->email} · {$customer->phone}",
            $customer->company ? "Empresa: {$customer->company}" : null,
            $this->booking->notes ? "Comentarios: {$this->booking->notes}" : null,
            $customer->no_show_count > 0 ? "Atención: este cliente tiene {$customer->no_show_count} inasistencia(s)." : null,
        ]));
    }

    protected function action(): ?array
    {
        return [
            $this->booking->status === BookingStatus::Pending ? 'Revisar y confirmar' : 'Ver en el panel',
            route('filament.admin.resources.bookings.view', $this->booking),
        ];
    }

    protected function whatsappMessage(): ?WhatsAppMessage
    {
        return $this->template('nueva_reserva_admin', [
            $this->booking->code,
            $this->booking->space->name,
            $this->booking->scheduleLabel(),
            $this->booking->customer->name,
            (string) $this->booking->customer->phone,
            $this->booking->status->getLabel(),
            route('filament.admin.resources.bookings.view', $this->booking),
        ]);
    }
}
