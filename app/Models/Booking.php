<?php

namespace App\Models;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'space_id', 'customer_id', 'starts_at', 'ends_at', 'attendees', 'notes',
    'status', 'status_reason', 'source', 'confirmed_at', 'cancelled_at', 'reminder_sent_at',
])]
#[Hidden(['manage_token'])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    /** Unambiguous characters for human-readable booking codes (no 0/O, 1/I/L). */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            $booking->code ??= static::generateCode();
            $booking->manage_token ??= Str::random(64);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'status' => BookingStatus::class,
            'source' => BookingSource::class,
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(BookingEvent::class)->latest('id');
    }

    /**
     * Bookings that keep their time slot occupied (pending or confirmed).
     */
    #[Scope]
    protected function holdingSlot(Builder $query): Builder
    {
        return $query->whereIn('status', BookingStatus::holdingSlot());
    }

    public static function generateCode(): string
    {
        do {
            $code = 'DL-';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
