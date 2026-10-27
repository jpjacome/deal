<?php

namespace App\Models;

use App\Enums\BookingMode;
use Database\Factories\SpaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'slug', 'type_label', 'description', 'capacity', 'booking_mode', 'requires_approval',
    'slot_minutes', 'min_minutes', 'max_minutes', 'buffer_minutes', 'lead_time_minutes',
    'horizon_days', 'cancellation_hours', 'price_label', 'is_bookable', 'is_active', 'sort',
])]
class Space extends Model
{
    /** @use HasFactory<SpaceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'booking_mode' => BookingMode::class,
            'requires_approval' => 'boolean',
            'is_bookable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function openingHours(): HasMany
    {
        return $this->hasMany(OpeningHour::class);
    }

    public function blockedPeriods(): HasMany
    {
        return $this->hasMany(BlockedPeriod::class);
    }

    #[Scope]
    protected function bookable(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_bookable', true);
    }
}
