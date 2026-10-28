<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\SpaceResource;
use App\Models\Space;
use App\Services\Availability;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only API v1 for the future mobile app: spaces and their availability.
 */
class SpaceController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SpaceResource::collection(Space::bookable()->orderBy('sort')->orderBy('name')->get());
    }

    public function show(Space $space): SpaceResource
    {
        abort_unless($space->is_active && $space->is_bookable, 404);

        return new SpaceResource($space);
    }

    /**
     * GET /api/v1/spaces/{slug}/availability?date=Y-m-d[&duration=minutes&attendees=n]
     * Without a date: status of the next 14 days.
     */
    public function availability(Request $request, Space $space, Availability $availability): JsonResponse
    {
        abort_unless($space->is_active && $space->is_bookable, 404);

        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'duration' => ['nullable', 'integer', 'min:15', 'max:1440'],
            'attendees' => ['nullable', 'integer', 'min:1'],
        ]);

        $attendees = (int) ($data['attendees'] ?? 1);
        $duration = isset($data['duration']) ? (int) $data['duration'] : null;

        if (empty($data['date'])) {
            return response()->json(['data' => [
                'days' => $availability->dayStatuses($space, today()->toImmutable(), 14, $duration, $attendees),
            ]]);
        }

        $day = CarbonImmutable::createFromFormat('!Y-m-d', $data['date']);

        if ($space->booking_mode === BookingMode::Hourly) {
            $slots = collect($availability->slots($space, $day, $duration ?? $space->min_minutes, $attendees))
                ->map(fn (array $slot) => ['start' => $slot['start']->format('H:i'), 'available' => $slot['available']])
                ->values();

            return response()->json(['data' => ['date' => $day->toDateString(), 'duration' => $duration ?? $space->min_minutes, 'slots' => $slots]]);
        }

        $bounds = $availability->dayBounds($space, $day);

        return response()->json(['data' => [
            'date' => $day->toDateString(),
            'open' => $bounds !== null,
            'available' => $bounds !== null && $availability->isDayAvailable($space, $day, $attendees),
            'hours' => $bounds ? ['from' => $bounds[0]->format('H:i'), 'to' => $bounds[1]->format('H:i')] : null,
        ]]);
    }
}
