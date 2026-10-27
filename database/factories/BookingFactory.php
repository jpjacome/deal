<?php

namespace Database\Factories;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDays(3)->setTime(10, 0)->toImmutable();

        return [
            'space_id' => Space::factory(),
            'customer_id' => Customer::factory(),
            'starts_at' => $start,
            'ends_at' => $start->addHours(2),
            'attendees' => 1,
            'status' => BookingStatus::Confirmed,
            'source' => BookingSource::Web,
        ];
    }

    public function status(BookingStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
