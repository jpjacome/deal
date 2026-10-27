<?php

namespace Database\Factories;

use App\Models\BlockedPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlockedPeriod>
 */
class BlockedPeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDays(3)->startOfDay()->toImmutable();

        return [
            'space_id' => null,
            'starts_at' => $start,
            'ends_at' => $start->endOfDay(),
            'reason' => 'Feriado',
        ];
    }
}
