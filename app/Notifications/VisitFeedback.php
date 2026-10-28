<?php

namespace App\Notifications;

use App\Models\Setting;

/**
 * To the customer, after their visit: ask how it went and invite a Google review.
 */
class VisitFeedback extends BookingNotification
{
    protected function subject(): string
    {
        return '¿Qué tal tu visita a '.config('app.name').'?';
    }

    protected function heading(): string
    {
        return 'Gracias por visitarnos';
    }

    protected function lines(): array
    {
        return [
            "Hola {$this->booking->customer->name}, esperamos que hayas tenido una buena jornada en {$this->booking->space->name}.",
            'Si tienes un minuto, tu reseña nos ayuda muchísimo a que más personas conozcan el espacio. Y si algo pudo ser mejor, responde a este correo: lo leemos todo.',
        ];
    }

    protected function action(): ?array
    {
        return ['Dejar una reseña en Google', (string) Setting::get('google_review_url')];
    }
}
