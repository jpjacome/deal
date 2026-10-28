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
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'name', 'slug', 'type_label', 'description', 'capacity', 'booking_mode', 'requires_approval',
    'slot_minutes', 'min_minutes', 'max_minutes', 'buffer_minutes', 'lead_time_minutes',
    'horizon_days', 'cancellation_hours', 'price_label', 'is_bookable', 'is_active', 'sort',
    'photo', 'amenities', 'price', 'allows_half_day', 'half_day_price',
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
            'amenities' => 'array',
            'price' => 'decimal:2',
            'allows_half_day' => 'boolean',
            'half_day_price' => 'decimal:2',
        ];
    }

    public function photoUrl(): ?string
    {
        return $this->photo ? Storage::disk('public')->url($this->photo) : null;
    }

    /**
     * Estimated price of a booking, or null when the space has no numeric price.
     * Hourly: per hour. Daily: per day (or half-day price). Capacity: per person per day.
     */
    public function estimatePrice(int $minutes, int $attendees = 1, bool $halfDay = false): ?float
    {
        if ($this->price === null) {
            return null;
        }

        $unit = $halfDay && $this->half_day_price !== null ? (float) $this->half_day_price : (float) $this->price;

        return round(match ($this->booking_mode) {
            BookingMode::Hourly => $unit * $minutes / 60,
            BookingMode::Daily => $unit,
            BookingMode::Capacity => $unit * $attendees,
        }, 2);
    }

    public function priceUnitLabel(): string
    {
        return match ($this->booking_mode) {
            BookingMode::Hourly => 'por hora',
            BookingMode::Daily => 'por día',
            BookingMode::Capacity => 'por persona al día',
        };
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
