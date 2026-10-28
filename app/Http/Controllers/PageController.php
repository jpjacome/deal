<?php

namespace App\Http\Controllers;

use App\Enums\BookingMode;
use App\Models\OpeningHour;
use App\Models\Setting;
use App\Models\Space;
use App\Services\Availability;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(Availability $availability): View
    {
        $spaces = Space::bookable()->orderBy('sort')->orderBy('name')->get();

        // "Libre hoy": next free start time today for each hourly space.
        $freeToday = $spaces
            ->filter(fn (Space $space) => $space->booking_mode === BookingMode::Hourly)
            ->map(fn (Space $space) => [
                'space' => $space,
                'start' => $availability->availableStartTimes($space, today()->toImmutable(), $space->min_minutes)[0] ?? null,
            ])
            ->filter(fn (array $row) => $row['start'] !== null)
            ->values();

        return view('home', ['spaces' => $spaces, 'freeToday' => $freeToday]);
    }

    public function contact(): View
    {
        $hours = OpeningHour::whereNull('space_id')
            ->orderBy('weekday')
            ->orderBy('opens_at')
            ->get()
            ->groupBy('weekday')
            ->map(fn ($ranges) => $ranges->map(fn (OpeningHour $h) => substr($h->opens_at, 0, 5).'–'.substr($h->closes_at, 0, 5))->join(' y '));

        return view('pages.contact', ['hours' => $hours, 'settings' => Setting::values()]);
    }

    public function privacy(): View
    {
        return $this->legal('Política de privacidad', Setting::get('privacy_policy') ?: view('legal.privacy-default')->render());
    }

    public function terms(): View
    {
        return $this->legal('Condiciones de reserva', Setting::get('booking_terms') ?: view('legal.terms-default')->render());
    }

    private function legal(string $title, string $markdown): View
    {
        return view('pages.legal', [
            'title' => $title,
            'body' => Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
        ]);
    }
}
