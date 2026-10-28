<?php

namespace App\Notifications;

use App\Models\Customer;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To frequent visitors: a monthly plan may suit them better than booking day by day.
 */
class MembershipNudge extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $visits)
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return filled($notifiable->routeNotificationFor('mail', $this)) ? ['mail'] : [];
    }

    public function toMail(Customer $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("{$notifiable->name}, ¿y si Deal fuera tu oficina?")
            ->greeting("Hola {$notifiable->name},")
            ->line("Ya nos visitaste {$this->visits} veces. ¡Gracias!")
            ->line((string) (Setting::get('membership_nudge_text') ?: 'Si trabajas con nosotros seguido, un plan mensual puede salirte mejor que reservar día por día, con tu lugar asegurado.'));

        if ($digits = Setting::whatsappDigits()) {
            $mail->action('Conversemos por WhatsApp', "https://wa.me/{$digits}?text=".rawurlencode('Hola, quiero información sobre los planes mensuales.'));
        }

        return $mail->salutation('El equipo de '.config('app.name'));
    }
}
