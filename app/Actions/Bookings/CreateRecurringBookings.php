<?php

namespace App\Actions\Bookings;

use App\Enums\BookingSource;
use App\Exceptions\BookingUnavailable;
use App\Models\Booking;
use App\Models\Space;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Weekly series for regular teams (staff only). Dates that aren't available are skipped and reported.
 */
class CreateRecurringBookings
{
    public const MAX_WEEKS = 26;

    public function __construct(private CreateBooking $createBooking) {}

    /**
     * @param  array{name: string, email?: ?string, phone?: ?string, company?: ?string}  $customer
     * @return array{created: list<Booking>, skipped: array<string, string>} skipped: Y-m-d => reason
     */
    public function handle(
        Space $space,
        CarbonImmutable $start,
        CarbonImmutable $end,
        CarbonImmutable $until,
        array $customer,
        int $attendees,
        ?string $notes,
        User $createdBy,
        bool $notifyCustomer = true,
    ): array {
        $seriesId = (string) Str::uuid();
        $created = [];
        $skipped = [];

        for ($week = 0; $week < self::MAX_WEEKS; $week++) {
            $from = $start->addWeeks($week);

            if ($from->startOfDay() > $until->startOfDay()) {
                break;
            }

            try {
                $created[] = $this->createBooking->handle(
                    $space,
                    $from,
                    $end->addWeeks($week),
                    $customer,
                    attendees: $attendees,
                    notes: $notes,
                    source: BookingSource::Admin,
                    createdBy: $createdBy,
                    // One confirmation for the whole series, not one email per week.
                    notifyCustomer: $notifyCustomer && $created === [],
                    seriesId: $seriesId,
                );
            } catch (BookingUnavailable $e) {
                $skipped[$from->toDateString()] = $e->getMessage();
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
