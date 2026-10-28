<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * "¿Perdiste tu enlace?": the customer's upcoming bookings with their private links.
 */
class CustomerBookingsLink extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Booking>  $bookings
     */
    public function __construct(public Collection $bookings) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tus reservas en '.config('app.name'))
            ->markdown('mail.customer-bookings', [
                'name' => $notifiable->name,
                'bookings' => $this->bookings->loadMissing('space'),
            ]);
    }
}
