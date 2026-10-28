<?php

namespace App\Services;

use App\Actions\Bookings\ChangeBookingStatus;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Setting;
use App\Notifications\BookingReminder;
use App\Notifications\MembershipNudge;
use App\Notifications\StaffBookingList;
use App\Notifications\VisitFeedback;
use App\Support\StaffRecipients;
use DomainException;

/**
 * Time-based booking housekeeping, run every few minutes by the scheduler (`bookings:tick`).
 */
class BookingAutomation
{
    public function __construct(private ChangeBookingStatus $changeStatus) {}

    /**
     * Pending requests nobody reviewed in time (or whose start already passed) release their slot.
     */
    public function expirePending(): int
    {
        $hours = (int) Setting::get('pending_expiry_hours');

        $expired = Booking::where('status', BookingStatus::Pending)
            ->where(fn ($query) => $query
                ->where('starts_at', '<=', now())
                ->when($hours > 0, fn ($query) => $query->orWhere('created_at', '<=', now()->subHours($hours))))
            ->get();

        return $expired->each(fn (Booking $booking) => $this->transition($booking, BookingStatus::Expired, 'No se confirmó a tiempo.'))->count();
    }

    /**
     * Nudge the team about requests waiting longer than the reminder threshold (once per booking).
     */
    public function remindStaffAboutPending(): int
    {
        $hours = (int) Setting::get('pending_reminder_hours');

        if ($hours <= 0) {
            return 0;
        }

        $waiting = Booking::where('status', BookingStatus::Pending)
            ->whereNull('pending_reminder_sent_at')
            ->where('created_at', '<=', now()->subHours($hours))
            ->orderBy('starts_at')
            ->get();

        if ($waiting->isEmpty()) {
            return 0;
        }

        StaffRecipients::notifiable()?->notify(new StaffBookingList(
            subject: "{$waiting->count()} ".($waiting->count() === 1 ? 'reserva espera' : 'reservas esperan').' confirmación',
            heading: 'Reservas por confirmar',
            intro: 'Estas solicitudes llevan un rato esperando. Si no se confirman a tiempo, expiran y el cliente recibe un aviso.',
            bookings: $waiting,
        ));

        Booking::whereKey($waiting->modelKeys())->update(['pending_reminder_sent_at' => now()]);

        return $waiting->count();
    }

    /**
     * Remind customers ahead of confirmed visits (skips bookings made very recently).
     */
    public function sendReminders(): int
    {
        $hours = (int) Setting::get('reminder_hours_before');

        if ($hours <= 0) {
            return 0;
        }

        $due = Booking::with(['space', 'customer'])
            ->where('status', BookingStatus::Confirmed)
            ->whereNull('reminder_sent_at')
            ->where('starts_at', '>', now())
            ->where('starts_at', '<=', now()->addHours($hours))
            ->where('created_at', '<=', now()->subHour())
            ->get();

        foreach ($due as $booking) {
            $booking->customer->notify(new BookingReminder($booking));
            $booking->update(['reminder_sent_at' => now()]);
        }

        return $due->count();
    }

    /**
     * Confirmed bookings that ended become completed, so staff only need to mark no-shows.
     * With a check-in grace period configured, bookings nobody checked into become no-shows.
     */
    public function closeFinished(): int
    {
        $grace = (int) Setting::get('checkin_grace_minutes');
        $count = 0;

        if ($grace > 0) {
            $missed = Booking::where('status', BookingStatus::Confirmed)
                ->whereNull('checked_in_at')
                ->where('starts_at', '<=', now()->subMinutes($grace))
                ->get();

            foreach ($missed as $booking) {
                $count += (int) $this->transition($booking, BookingStatus::NoShow, "Sin registro de llegada a los {$grace} minutos.");
            }
        }

        $finished = Booking::where('status', BookingStatus::Confirmed)->where('ends_at', '<=', now())->get();

        foreach ($finished as $booking) {
            $count += (int) $this->transition($booking, BookingStatus::Completed);
        }

        return $count;
    }

    /**
     * After a visit: feedback request (when a review link is set) and, for frequent visitors, a plan suggestion.
     */
    public function sendFollowUps(): int
    {
        $sent = 0;
        $reviewUrl = Setting::get('google_review_url');
        $nudgeAfter = (int) Setting::get('membership_nudge_after');

        $visits = Booking::with(['space', 'customer'])
            ->where('status', BookingStatus::Completed)
            ->whereNull('feedback_sent_at')
            ->whereBetween('ends_at', [now()->subDays(3), now()->subHours(2)])
            ->get();

        foreach ($visits as $booking) {
            $booking->update(['feedback_sent_at' => now()]);

            if (filled($reviewUrl) && $booking->customer->email) {
                $booking->customer->notify(new VisitFeedback($booking));
                $sent++;
            }

            $sent += (int) $this->nudgeMembership($booking->customer, $nudgeAfter);
        }

        return $sent;
    }

    /**
     * Morning email with today's bookings (only when there are any).
     */
    public function sendDailyAgenda(): bool
    {
        $today = Booking::holdingSlot()
            ->whereDate('starts_at', today())
            ->orderBy('starts_at')
            ->get();

        if ($today->isEmpty()) {
            return false;
        }

        StaffRecipients::notifiable()?->notify(new StaffBookingList(
            subject: "Agenda de hoy: {$today->count()} ".($today->count() === 1 ? 'reserva' : 'reservas'),
            heading: 'Agenda de hoy',
            intro: ucfirst(today()->translatedFormat('l j \d\e F')).'.',
            bookings: $today,
        ));

        return true;
    }

    private function nudgeMembership(Customer $customer, int $nudgeAfter): bool
    {
        if ($nudgeAfter <= 0 || $customer->membership_nudged_at !== null || ! $customer->email) {
            return false;
        }

        $visits = $customer->bookings()->where('status', BookingStatus::Completed)->count();

        if ($visits < $nudgeAfter) {
            return false;
        }

        $customer->notify(new MembershipNudge($visits));
        $customer->forceFill(['membership_nudged_at' => now()])->save();

        return true;
    }

    private function transition(Booking $booking, BookingStatus $to, ?string $reason = null): bool
    {
        try {
            $this->changeStatus->handle($booking, $to, reason: $reason);

            return true;
        } catch (DomainException) {
            return false; // status changed meanwhile
        }
    }
}
