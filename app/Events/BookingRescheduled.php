<?php

namespace App\Events;

use App\Models\Booking;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class BookingRescheduled implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public Booking $booking,
        public CarbonImmutable $previousStart,
        public CarbonImmutable $previousEnd,
        public ?User $changedBy = null,
        public bool $byCustomer = false,
    ) {}
}
