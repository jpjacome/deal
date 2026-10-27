<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class ChangeBookingStatus
{
    /**
     * @throws DomainException When the status change isn't allowed.
     */
    public function handle(Booking $booking, BookingStatus $to, ?User $changedBy = null, ?string $reason = null): Booking
    {
        return DB::transaction(function () use ($booking, $to, $changedBy, $reason) {
            $booking = Booking::whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            $from = $booking->status;

            if (! $from->canTransitionTo($to)) {
                throw new DomainException("Una reserva {$from->getLabel()} no puede pasar a {$to->getLabel()}.");
            }

            $booking->status = $to;
            $booking->status_reason = $reason;

            match ($to) {
                BookingStatus::Confirmed => $booking->confirmed_at = now(),
                BookingStatus::Cancelled => $booking->cancelled_at = now(),
                BookingStatus::NoShow => $booking->customer()->increment('no_show_count'),
                default => null,
            };

            $booking->save();

            $booking->events()->create([
                'user_id' => $changedBy?->id,
                'from_status' => $from,
                'to_status' => $to,
                'note' => $reason,
            ]);

            return $booking;
        });
    }
}
