<?php

namespace App\Notifications;

use App\Models\Space;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\WhatsApp\WhatsAppMessage;
use App\Services\WhatsApp;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To someone on the waitlist: a spot opened up on the day they wanted.
 */
class WaitlistSpotAvailable extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Space $space, public CarbonImmutable $date)
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return array_values(array_filter([
            'mail',
            WhatsApp::isEnabled() && filled($notifiable->routeNotificationFor('whatsapp', $this)) ? WhatsAppChannel::class : null,
        ]));
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Se liberó un espacio en {$this->space->name}")
            ->greeting("Hola {$notifiable->name},")
            ->line("Se liberó disponibilidad en **{$this->space->name}** para el **{$this->day()}**.")
            ->line('Avisamos a todas las personas en lista de espera: quien reserve primero se queda con el espacio.')
            ->action('Reservar ahora', $this->bookUrl())
            ->salutation('¡Te esperamos!');
    }

    public function toWhatsapp(object $notifiable): WhatsAppMessage
    {
        return new WhatsAppMessage('espacio_liberado', [$notifiable->name, $this->space->name, $this->day(), $this->bookUrl()]);
    }

    private function day(): string
    {
        return $this->date->translatedFormat('l j \d\e F');
    }

    private function bookUrl(): string
    {
        return route('bookings.create', ['espacio' => $this->space->slug, 'fecha' => $this->date->toDateString()]);
    }
}
