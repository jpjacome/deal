<?php

namespace App\Http\Controllers;

use App\Actions\Bookings\ChangeBookingStatus;
use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * "Mi reserva": the customer's private page, reached through the unguessable link in their notifications.
 */
class ManageBookingController extends Controller
{
    public function show(string $token): View
    {
        $booking = $this->findBooking($token);

        $calendarUrl = 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => "{$booking->space->name} · ".config('app.name'),
            'dates' => $booking->starts_at->utc()->format('Ymd\THis\Z').'/'.$booking->ends_at->utc()->format('Ymd\THis\Z'),
            'details' => "Reserva {$booking->code}",
        ]);

        return view('bookings.show', compact('booking', 'calendarUrl'));
    }

    public function cancel(string $token, ChangeBookingStatus $changeStatus): RedirectResponse
    {
        $booking = $this->findBooking($token);

        abort_unless($booking->canBeCancelledByCustomer(), 403);

        $changeStatus->handle($booking, BookingStatus::Cancelled, reason: 'Cancelada por el cliente.');

        return redirect()->route('bookings.show', $token)->with('cancelled', true);
    }

    private function findBooking(string $token): Booking
    {
        return Booking::with(['space', 'customer'])->where('manage_token', $token)->firstOrFail();
    }
}
