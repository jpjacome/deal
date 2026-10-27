<?php

namespace Database\Factories;

use App\Enums\BookingMode;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Space>
 */
class SpaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Sala '.fake()->unique()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'capacity' => 6,
            'booking_mode' => BookingMode::Hourly,
            'requires_approval' => true,
            'slot_minutes' => 60,
            'min_minutes' => 60,
            'max_minutes' => 480,
            'buffer_minutes' => 0,
            'lead_time_minutes' => 120,
            'horizon_days' => 60,
            'cancellation_hours' => 24,
            'is_bookable' => true,
            'is_active' => true,
        ];
    }

    public function daily(): static
    {
        return $this->state(['booking_mode' => BookingMode::Daily, 'capacity' => 4]);
    }

    public function capacity(int $seats = 10): static
    {
        return $this->state(['booking_mode' => BookingMode::Capacity, 'capacity' => $seats]);
    }

    public function autoConfirm(): static
    {
        return $this->state(['requires_approval' => false]);
    }
}
