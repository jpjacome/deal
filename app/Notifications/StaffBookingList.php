<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * To Deal's team: a list of bookings (pending reminders, daily agenda).
 */
class StaffBookingList extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Booking>  $bookings
     */
    public function __construct(
        public string $subject,
        public string $heading,
        public string $intro,
        public Collection $bookings,
    ) {}

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
            ->subject($this->subject)
            ->markdown('mail.staff-list', [
                'heading' => $this->heading,
                'intro' => $this->intro,
                'bookings' => $this->bookings->loadMissing(['space', 'customer']),
            ]);
    }
}
