<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

/**
 * Where team alerts go: the emails/WhatsApp numbers set in Ajustes, or the admins' emails as a fallback.
 */
class StaffRecipients
{
    public static function notifiable(): ?AnonymousNotifiable
    {
        $emails = array_values(array_filter((array) Setting::get('notify_emails')));

        if ($emails === []) {
            $emails = User::where('role', UserRole::Admin)->pluck('email')->all();
        }

        $whatsapp = array_values(array_filter((array) Setting::get('notify_whatsapp')));

        if ($emails === [] && $whatsapp === []) {
            return null;
        }

        $notifiable = Notification::route('mail', $emails);

        return $whatsapp === [] ? $notifiable : $notifiable->route('whatsapp', $whatsapp);
    }
}
