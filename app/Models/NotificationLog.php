<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Every email/WhatsApp message sent (or failed) for a booking.
 */
#[Fillable(['booking_id', 'channel', 'template', 'recipient', 'status', 'provider_id', 'error'])]
class NotificationLog extends Model
{
    const UPDATED_AT = null;

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
