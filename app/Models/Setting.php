<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Key/value settings editable from the admin ("Ajustes"). Values are JSON-encoded and cached.
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    private const CACHE_KEY = 'settings.all';

    /**
     * Defaults used until Deal saves its own values.
     *
     * @var array<string, mixed>
     */
    public const DEFAULTS = [
        'venue_address' => 'Cuenca, Ecuador',
        'maps_url' => null,
        'whatsapp_number' => null,
        'contact_email' => null,
        'notify_emails' => [],
        'notify_whatsapp' => [],
        'arrival_info' => null,
        'pending_expiry_hours' => 24,
        'pending_reminder_hours' => 4,
        'reminder_hours_before' => 24,
        'no_show_threshold' => 3,
        'checkin_grace_minutes' => 0,
        'google_review_url' => null,
        'membership_nudge_after' => 3,
        'membership_nudge_text' => null,
        'privacy_policy' => null,
        'privacy_version' => '2026-10-27',
        'booking_terms' => null,
        'screen_token' => null,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());

        return $stored[$key] ?? $default ?? self::DEFAULTS[$key] ?? null;
    }

    /**
     * All settings, defaults filled in.
     *
     * @return array<string, mixed>
     */
    public static function values(): array
    {
        return collect(self::DEFAULTS)->map(fn ($default, $key) => static::get($key))->all();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    /**
     * Phone number for wa.me links (digits only), or null when not configured.
     */
    public static function whatsappDigits(): ?string
    {
        $number = preg_replace('/\D/', '', (string) static::get('whatsapp_number'));

        return $number !== '' ? $number : null;
    }
}
