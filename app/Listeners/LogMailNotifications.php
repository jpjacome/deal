<?php

namespace App\Listeners;

use App\Models\NotificationLog;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Records every email sent about a booking (WhatsApp messages are logged by the WhatsApp service).
 */
class LogMailNotifications
{
    public function handleSent(NotificationSent $event): void
    {
        $this->log($event->channel, $event->notifiable, $event->notification, 'sent');
    }

    public function handleFailed(NotificationFailed $event): void
    {
        $this->log($event->channel, $event->notifiable, $event->notification, 'failed', json_encode($event->data));
    }

    private function log(string $channel, object $notifiable, object $notification, string $status, ?string $error = null): void
    {
        if ($channel !== 'mail') {
            return;
        }

        NotificationLog::create([
            'booking_id' => isset($notification->booking) ? $notification->booking->id : null,
            'channel' => 'mail',
            'template' => class_basename($notification),
            'recipient' => implode(', ', (array) $notifiable->routeNotificationFor('mail', $notification)),
            'status' => $status,
            'error' => $error,
        ]);
    }
}
