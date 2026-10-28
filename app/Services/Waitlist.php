<?php

namespace App\Services;

use App\Models\Space;
use App\Models\WaitlistEntry;
use App\Notifications\WaitlistSpotAvailable;
use Carbon\CarbonImmutable;

class Waitlist
{
    /**
     * Tell everyone waiting for that space and day that something opened up (first to book gets it).
     */
    public function notifyFor(Space $space, CarbonImmutable $date): int
    {
        if ($date->isPast() && ! $date->isToday()) {
            return 0;
        }

        $entries = WaitlistEntry::whereBelongsTo($space)
            ->whereDate('date', $date->toDateString())
            ->whereNull('notified_at')
            ->get();

        foreach ($entries as $entry) {
            $entry->notify(new WaitlistSpotAvailable($space, $date));
            $entry->update(['notified_at' => now()]);
        }

        return $entries->count();
    }
}
