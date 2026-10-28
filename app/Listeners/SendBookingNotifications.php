<?php

namespace App\Listeners;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Events\BookingCreated;
use App\Events\BookingRescheduled;
use App\Events\BookingStatusChanged;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingCancelled;
use App\Notifications\BookingConfirmed;
use App\Notifications\BookingExpired;
use App\Notifications\BookingRejected;
use App\Notifications\BookingRequested;
use App\Notifications\BookingRescheduled as BookingRescheduledNotification;
use App\Notifications\StaffBookingCancelled;
use App\Notifications\StaffNewBooking;
use App\Services\Waitlist;
use App\Support\StaffRecipients;
use Filament\Actions\Action;
use Filament\Notifications\Notification as PanelNotification;

/**
 * Decides who hears about each booking change: the customer, Deal's team, the waitlist.
 */
class SendBookingNotifications
{
    public function __construct(private Waitlist $waitlist) {}

    public function handleCreated(BookingCreated $event): void
    {
        $booking = $event->booking->loadMissing(['space', 'customer']);

        if ($event->notifyCustomer) {
            $booking->customer->notify(
                $booking->status === BookingStatus::Pending ? new BookingRequested($booking) : new BookingConfirmed($booking)
            );
        }

        // Staff already know about bookings they create themselves.
        if ($booking->source !== BookingSource::Admin) {
            StaffRecipients::notifiable()?->notify(new StaffNewBooking($booking));

            $this->notifyPanel(
                $booking,
                $booking->status === BookingStatus::Pending ? 'Nueva reserva por confirmar' : 'Nueva reserva',
                $booking->status === BookingStatus::Pending ? 'warning' : 'success',
            );
        }
    }

    public function handleStatusChanged(BookingStatusChanged $event): void
    {
        $booking = $event->booking->loadMissing(['space', 'customer']);
        $customer = $booking->customer;

        match ($event->to) {
            BookingStatus::Confirmed => $customer->notify(new BookingConfirmed($booking)),
            BookingStatus::Rejected => $customer->notify(new BookingRejected($booking)),
            BookingStatus::Expired => $customer->notify(new BookingExpired($booking)),
            BookingStatus::Cancelled => $customer->notify(new BookingCancelled($booking, $event->byCustomer)),
            default => null,
        };

        if ($event->to === BookingStatus::Cancelled && $event->byCustomer) {
            StaffRecipients::notifiable()?->notify(new StaffBookingCancelled($booking));
            $this->notifyPanel($booking, 'Un cliente canceló su reserva', 'danger');
        }

        // A slot opened up: tell whoever was waiting for it.
        if (in_array($event->to, [BookingStatus::Cancelled, BookingStatus::Rejected, BookingStatus::Expired], true)) {
            $this->waitlist->notifyFor($booking->space, $booking->starts_at);
        }
    }

    public function handleRescheduled(BookingRescheduled $event): void
    {
        $booking = $event->booking->loadMissing(['space', 'customer']);

        $booking->customer->notify(new BookingRescheduledNotification($booking));

        if ($event->byCustomer) {
            $this->notifyPanel($booking, 'Un cliente cambió el horario de su reserva', 'info');
        }

        if (! $event->previousStart->isSameDay($booking->starts_at)) {
            $this->waitlist->notifyFor($booking->space, $event->previousStart);
        }
    }

    /**
     * Bell notification in the admin panel for every team member.
     */
    private function notifyPanel(Booking $booking, string $title, string $color): void
    {
        PanelNotification::make()
            ->title($title)
            ->body("{$booking->code} · {$booking->space->name} · {$booking->scheduleLabel()} · {$booking->customer->name}")
            ->status($color)
            ->actions([
                Action::make('view')
                    ->label('Ver reserva')
                    ->url(route('filament.admin.resources.bookings.view', $booking))
                    ->markAsRead(),
            ])
            ->sendToDatabase(User::all());
    }
}
