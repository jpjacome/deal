<?php

namespace App\Models;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Services\Availability;
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
    'series_id', 'pending_reminder_sent_at', 'attendance_confirmed_at', 'checked_in_at', 'feedback_sent_at',
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
            'pending_reminder_sent_at' => 'datetime',
            'attendance_confirmed_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'feedback_sent_at' => 'datetime',
        ];
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class)->latest('id');
    }

    /**
     * "Miércoles 4 de noviembre, 10:00–12:00"
     */
    public function scheduleLabel(): string
    {
        return ucfirst($this->starts_at->translatedFormat('l j \d\e F')).', '.$this->starts_at->format('H:i').'–'.$this->ends_at->format('H:i');
    }

    /**
     * Estimated price from the space's numeric price, or null when the space has none.
     */
    public function estimatedPrice(): ?float
    {
        $minutes = (int) $this->starts_at->diffInMinutes($this->ends_at);
        $halfDay = false;

        if ($this->space->booking_mode->isFullDay()) {
            $bounds = app(Availability::class)->dayBounds($this->space, $this->starts_at);
            $halfDay = $bounds !== null && $minutes < (int) $bounds[0]->diffInMinutes($bounds[1]);
        }

        return $this->space->estimatePrice($minutes, $this->attendees, $halfDay);
    }

    public function manageUrl(): string
    {
        return route('bookings.show', $this->manage_token);
    }

    /**
     * Customers can reschedule online under the same window as cancelling.
     */
    public function canBeRescheduledByCustomer(): bool
    {
        return $this->canBeCancelledByCustomer();
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

    /**
     * Customers can cancel online until the space's cancellation window closes.
     */
    public function canBeCancelledByCustomer(): bool
    {
        return in_array($this->status, BookingStatus::holdingSlot(), true)
            && now()->addHours($this->space->cancellation_hours) <= $this->starts_at;
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
