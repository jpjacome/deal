<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Setting;
use App\Models\Space;
use App\Support\QrCode;
use Illuminate\View\View;

/**
 * Reception TV/tablet screen and printable door signs.
 */
class ScreenController extends Controller
{
    /**
     * "Hoy en Deal": today's schedule per space, refreshed automatically. Reached via a private URL.
     */
    public function show(string $token): View
    {
        $expected = (string) Setting::get('screen_token');
        abort_unless($expected !== '' && hash_equals($expected, $token), 404);

        $bookings = Booking::with('customer:id,name,company')
            ->holdingSlot()
            ->whereDate('starts_at', today())
            ->orderBy('starts_at')
            ->get()
            ->groupBy('space_id');

        $spaces = Space::where('is_active', true)->orderBy('sort')->orderBy('name')->get()
            ->map(function (Space $space) use ($bookings) {
                $today = $bookings->get($space->id, collect());
                $current = $today->first(fn (Booking $b) => $b->starts_at <= now() && $b->ends_at > now());

                return [
                    'space' => $space,
                    'current' => $current,
                    'upcoming' => $today->filter(fn (Booking $b) => $b->starts_at > now())->values(),
                ];
            });

        return view('screen.show', ['spaces' => $spaces]);
    }

    /**
     * Printable sign for a room door: scan to book this space.
     */
    public function doorSign(Space $space): View
    {
        abort_unless($space->is_active && $space->is_bookable, 404);

        $url = route('bookings.create', ['espacio' => $space->slug, 'fecha' => today()->toDateString()]);

        return view('screen.door-sign', ['space' => $space, 'qr' => QrCode::svg($url), 'url' => $url]);
    }
}
