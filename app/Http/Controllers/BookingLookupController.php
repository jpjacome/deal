<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Customer;
use App\Notifications\CustomerBookingsLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * "¿Perdiste tu enlace?": email the customer links to their upcoming bookings.
 * The response is the same whether or not the email exists, so it can't be used to probe customers.
 */
class BookingLookupController extends Controller
{
    public function show(): View
    {
        return view('bookings.lookup');
    }

    public function send(Request $request): RedirectResponse
    {
        $email = Str::lower(trim((string) $request->validate(['email' => ['required', 'email', 'max:190']])['email']));

        $customer = Customer::where('email', $email)->first();

        if ($customer) {
            $bookings = Booking::whereBelongsTo($customer)->holdingSlot()->where('ends_at', '>=', now())->orderBy('starts_at')->get();

            if ($bookings->isNotEmpty()) {
                $customer->notify(new CustomerBookingsLink($bookings));
            }
        }

        return back()->with('sent', true);
    }
}
