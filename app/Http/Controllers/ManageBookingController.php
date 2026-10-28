<?php

namespace App\Http\Controllers;

use App\Actions\Bookings\ChangeBookingStatus;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Setting;
use App\Support\Ics;
use App\Support\QrCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * "Mi reserva": the customer's private page, reached through the unguessable link in their notifications.
 */
class ManageBookingController extends Controller
{
    public function show(string $token): View
    {
        $booking = $this->findBooking($token);
        $isActive = in_array($booking->status, BookingStatus::holdingSlot(), true);

        $calendarUrl = 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => "{$booking->space->name} · ".config('app.name'),
            'dates' => $booking->starts_at->utc()->format('Ymd\THis\Z').'/'.$booking->ends_at->utc()->format('Ymd\THis\Z'),
            'details' => "Reserva {$booking->code}\n{$booking->manageUrl()}",
            'location' => (string) Setting::get('venue_address'),
        ]);

        $whatsappDigits = Setting::whatsappDigits();

        return view('bookings.show', [
            'booking' => $booking,
            'isActive' => $isActive,
            'calendarUrl' => $calendarUrl,
            'settings' => Setting::values(),
            'whatsappUrl' => $whatsappDigits
                ? "https://wa.me/{$whatsappDigits}?text=".rawurlencode("Hola, tengo la reserva {$booking->code} ({$booking->scheduleLabel()}).")
                : null,
            // Reception scans it to open the booking in the panel and register the arrival.
            'checkInQr' => $booking->status === BookingStatus::Confirmed && ! $booking->ends_at->isPast()
                ? QrCode::svg(route('filament.admin.resources.bookings.view', $booking))
                : null,
        ]);
    }

    public function calendar(string $token): Response
    {
        $booking = $this->findBooking($token);

        return response(Ics::booking($booking), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"reserva-{$booking->code}.ics\"",
        ]);
    }

    public function cancel(string $token, ChangeBookingStatus $changeStatus): RedirectResponse
    {
        $booking = $this->findBooking($token);

        abort_unless($booking->canBeCancelledByCustomer(), 403);

        $changeStatus->handle($booking, BookingStatus::Cancelled, reason: 'Cancelada por el cliente.', byCustomer: true);

        return redirect()->route('bookings.show', $token)->with('cancelled', true);
    }

    public function reschedule(string $token): View
    {
        $booking = $this->findBooking($token);

        abort_unless($booking->canBeRescheduledByCustomer(), 403);

        return view('bookings.reschedule', ['booking' => $booking]);
    }

    private function findBooking(string $token): Booking
    {
        return Booking::with(['space', 'customer'])->where('manage_token', $token)->firstOrFail();
    }
}
