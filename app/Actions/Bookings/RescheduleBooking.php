<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Events\BookingRescheduled;
use App\Exceptions\BookingUnavailable;
use App\Models\Booking;
use App\Models\Space;
use App\Models\User;
use App\Services\Availability;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Move a booking to a new time (and optionally another space) in one step: the old slot is only
 * released once the new one is secured.
 */
class RescheduleBooking
{
    public function __construct(private Availability $availability) {}

    /**
     * @throws BookingUnavailable|DomainException
     */
    public function handle(
        Booking $booking,
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?Space $space = null,
        ?User $changedBy = null,
        bool $byCustomer = false,
    ): Booking {
        return DB::transaction(function () use ($booking, $start, $end, $space, $changedBy, $byCustomer) {
            $space = Space::whereKey(($space ?? $booking->space)->getKey())->lockForUpdate()->firstOrFail();
            $booking = Booking::whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($booking->status, BookingStatus::holdingSlot(), true)) {
                throw new DomainException('Solo se pueden cambiar reservas pendientes o confirmadas.');
            }

            $this->availability->ensureBookable($space, $start, $end, $booking->attendees, strict: $changedBy === null, ignore: $booking);

            $previousStart = $booking->starts_at;
            $previousEnd = $booking->ends_at;
            $previousSpace = $booking->space->name;
            $previousStatus = $booking->status;

            $booking->fill([
                'space_id' => $space->id,
                'starts_at' => $start,
                'ends_at' => $end,
                'reminder_sent_at' => null,
                'attendance_confirmed_at' => null,
            ]);

            // Customers moving a booking in a space that needs approval go back to pending.
            if ($byCustomer && $space->requires_approval && $booking->status === BookingStatus::Confirmed) {
                $booking->status = BookingStatus::Pending;
                $booking->confirmed_at = null;
            }

            $booking->save();
            $booking->setRelation('space', $space);

            $booking->events()->create([
                'user_id' => $changedBy?->id,
                'from_status' => $previousStatus,
                'to_status' => $booking->status,
                'note' => "Cambio de horario: antes {$previousSpace}, {$previousStart->format('d/m H:i')}–{$previousEnd->format('H:i')}".($byCustomer ? ' (por el cliente)' : ''),
            ]);

            BookingRescheduled::dispatch($booking, $previousStart, $previousEnd, $changedBy, $byCustomer);

            return $booking;
        });
    }
}
