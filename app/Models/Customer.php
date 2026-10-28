<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'company', 'whatsapp_opt_in', 'privacy_accepted_at', 'privacy_version', 'notes', 'tags'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'whatsapp_opt_in' => 'boolean',
            'privacy_accepted_at' => 'datetime',
            'tags' => 'array',
            'membership_nudged_at' => 'datetime',
        ];
    }

    /**
     * WhatsApp number for notifications: only when the customer opted in.
     */
    public function routeNotificationForWhatsapp(): ?string
    {
        return $this->whatsapp_opt_in ? $this->phone : null;
    }

    public function hasReachedNoShowLimit(): bool
    {
        $threshold = (int) Setting::get('no_show_threshold');

        return $threshold > 0 && $this->no_show_count >= $threshold;
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
