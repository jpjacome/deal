<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Support\Ics;
use Illuminate\Http\Response;

/**
 * Private iCalendar feed of all bookings, for staff to subscribe from Google/Apple Calendar.
 */
class CalendarFeedController extends Controller
{
    public function __invoke(string $token): Response
    {
        abort_unless(User::where('calendar_token', $token)->exists(), 404);

        $bookings = Booking::with(['space', 'customer'])
            ->whereIn('status', [...BookingStatus::holdingSlot(), BookingStatus::Completed])
            ->whereBetween('starts_at', [now()->subDays(30), now()->addDays(180)])
            ->orderBy('starts_at')
            ->get();

        return response(Ics::feed($bookings), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="deal-reservas.ics"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
