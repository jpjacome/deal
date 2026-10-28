<?php

namespace App\Notifications\WhatsApp;

/**
 * WhatsApp message templates. Submit each one to Meta (category: Utility, language: es) with exactly
 * this name and body; {{n}} are the variables filled in by the app, in order.
 */
class Templates
{
    public const ALL = [
        'reserva_recibida' => [
            'body' => 'Hola {{1}}, recibimos tu solicitud de reserva {{2}} para {{3}} el {{4}}. Te avisaremos cuando la confirmemos. Puedes verla aquí: {{5}} . Gracias por elegirnos.',
        ],
        'reserva_confirmada' => [
            'body' => 'Hola {{1}}, tu reserva {{2}} está confirmada: {{3}}, {{4}}. Dirección: {{5}}. Para ver o cancelar tu reserva: {{6}} . ¡Te esperamos!',
        ],
        'reserva_rechazada' => [
            'body' => 'Hola {{1}}, no pudimos confirmar tu reserva {{2}} ({{3}}, {{4}}). Motivo: {{5}}. Escríbenos si quieres buscar otro horario.',
        ],
        'reserva_cancelada' => [
            'body' => 'Hola {{1}}, tu reserva {{2}} ({{3}}, {{4}}) fue cancelada. {{5}} Si tienes dudas, responde a este mensaje.',
        ],
        'reserva_expirada' => [
            'body' => 'Hola {{1}}, tu solicitud {{2}} ({{3}}, {{4}}) expiró antes de que pudiéramos confirmarla. Puedes elegir otro horario aquí: {{5}} . Disculpa las molestias.',
        ],
        'reserva_reprogramada' => [
            'body' => 'Hola {{1}}, tu reserva {{2}} cambió a: {{3}}, {{4}}. Detalles: {{5}} . ¡Te esperamos!',
        ],
        'recordatorio_reserva' => [
            'body' => 'Hola {{1}}, te recordamos tu reserva {{2}}: {{3}}, {{4}}. ¿Nos confirmas tu asistencia? Usa los botones de abajo.',
            'buttons' => ['Confirmo asistencia', 'Cancelar reserva'],
        ],
        'espacio_liberado' => [
            'body' => 'Hola {{1}}, se liberó un espacio en {{2}} para el {{3}}. Reserva antes que otra persona aquí: {{4}} . ¡Te esperamos!',
        ],
        'nueva_reserva_admin' => [
            'body' => 'Nueva reserva {{1}}: {{2}}, {{3}}. Cliente: {{4}} ({{5}}). Estado: {{6}}. Revisar en el panel: {{7}} .',
        ],
        'reserva_cancelada_admin' => [
            'body' => 'El cliente canceló la reserva {{1}}: {{2}}, {{3}}. Cliente: {{4}}. Ver en el panel: {{5}} .',
        ],
    ];
}
