<?php

namespace App\Notifications\Channels;

use App\Services\WhatsApp;
use Illuminate\Notifications\Notification;

class WhatsAppChannel
{
    public function __construct(private WhatsApp $whatsApp) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $recipients = array_filter((array) $notifiable->routeNotificationFor('whatsapp', $notification));

        if ($recipients === [] || ! method_exists($notification, 'toWhatsapp')) {
            return;
        }

        $message = $notification->toWhatsapp($notifiable);

        if ($message === null) {
            return;
        }

        foreach ($recipients as $to) {
            $this->whatsApp->send($to, $message);
        }
    }
}
