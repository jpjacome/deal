<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\WhatsApp\WhatsAppMessage;
use App\Services\WhatsApp;
use App\Support\Ics;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base for messages about one booking: email (shared layout) plus an optional WhatsApp template.
 */
abstract class BookingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking)
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if (filled($notifiable->routeNotificationFor('mail', $this))) {
            $channels[] = 'mail';
        }

        if (WhatsApp::isEnabled() && filled($notifiable->routeNotificationFor('whatsapp', $this)) && $this->whatsappMessage() !== null) {
            $channels[] = WhatsAppChannel::class;
        }

        return $channels;
    }

    abstract protected function subject(): string;

    abstract protected function heading(): string;

    /**
     * Paragraphs shown above the booking details.
     *
     * @return list<string>
     */
    abstract protected function lines(): array;

    protected function whatsappMessage(): ?WhatsAppMessage
    {
        return null;
    }

    /**
     * @return array{0: string, 1: string}|null [label, url]
     */
    protected function action(): ?array
    {
        return ['Ver mi reserva', $this->booking->manageUrl()];
    }

    protected function showsLocation(): bool
    {
        return false;
    }

    protected function attachesCalendar(): bool
    {
        return false;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->booking->loadMissing(['space', 'customer']);

        $mail = (new MailMessage)
            ->subject($this->subject())
            ->markdown('mail.booking', [
                'booking' => $this->booking,
                'heading' => $this->heading(),
                'lines' => $this->lines(),
                'action' => $this->action(),
                'showsLocation' => $this->showsLocation(),
            ]);

        if ($this->attachesCalendar()) {
            $mail->attachData(Ics::booking($this->booking), "reserva-{$this->booking->code}.ics", ['mime' => 'text/calendar']);
        }

        return $mail;
    }

    public function toWhatsapp(object $notifiable): ?WhatsAppMessage
    {
        $this->booking->loadMissing(['space', 'customer']);

        return $this->whatsappMessage();
    }

    /**
     * @param  list<string>  $parameters
     * @param  list<string>  $buttonPayloads
     */
    protected function template(string $name, array $parameters, array $buttonPayloads = []): WhatsAppMessage
    {
        return new WhatsAppMessage($name, $parameters, $buttonPayloads, $this->booking->id);
    }

    /**
     * Template variables most messages start with: name, code, space, schedule.
     *
     * @return list<string>
     */
    protected function basics(): array
    {
        return [
            $this->booking->customer->name,
            $this->booking->code,
            $this->booking->space->name,
            $this->booking->scheduleLabel(),
        ];
    }
}
