<?php

namespace App\Notifications;

use App\Notifications\WhatsApp\WhatsAppMessage;

/**
 * To the customer: Deal could not accept the request.
 */
class BookingRejected extends BookingNotification
{
    protected function subject(): string
    {
        return "No pudimos confirmar tu reserva {$this->booking->code}";
    }

    protected function heading(): string
    {
        return 'No pudimos confirmar tu reserva';
    }

    protected function lines(): array
    {
        return array_values(array_filter([
            "Hola {$this->booking->customer->name}, lo sentimos: no podemos recibirte en el horario que pediste.",
            $this->booking->status_reason ? "Motivo: {$this->booking->status_reason}" : null,
            'Puedes elegir otro horario cuando quieras.',
        ]));
    }

    protected function action(): ?array
    {
        return ['Buscar otro horario', route('bookings.create', ['espacio' => $this->booking->space->slug])];
    }

    protected function whatsappMessage(): ?WhatsAppMessage
    {
        return $this->template('reserva_rechazada', [...$this->basics(), $this->booking->status_reason ?: 'sin motivo indicado']);
    }
}
