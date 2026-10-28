<?php

namespace App\Services;

use App\Models\NotificationLog;
use App\Notifications\WhatsApp\WhatsAppMessage;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sends template messages through the Meta WhatsApp Cloud API and logs every attempt.
 * A failed WhatsApp message never breaks a booking; email remains the guaranteed channel.
 */
class WhatsApp
{
    public static function isEnabled(): bool
    {
        return filled(config('services.whatsapp.token')) && filled(config('services.whatsapp.phone_number_id'));
    }

    public function send(string $to, WhatsAppMessage $message): ?string
    {
        if (! self::isEnabled()) {
            return null;
        }

        try {
            $response = Http::withToken(config('services.whatsapp.token'))
                ->timeout(10)
                ->retry(2, 500, throw: false)
                ->post($this->endpoint(), $message->toPayload($to));

            if ($response->failed()) {
                $this->log($message, $to, 'failed', error: $response->json('error.message') ?? $response->body());

                return null;
            }

            $providerId = $response->json('messages.0.id');
            $this->log($message, $to, 'sent', $providerId);

            return $providerId;
        } catch (Throwable $e) {
            report($e);
            $this->log($message, $to, 'failed', error: $e->getMessage());

            return null;
        }
    }

    /**
     * Free-text reply. Only allowed within 24 h of the customer's last message (e.g. right after a button tap).
     */
    public function reply(string $to, string $text, ?int $bookingId = null): void
    {
        if (! self::isEnabled()) {
            return;
        }

        try {
            $response = Http::withToken(config('services.whatsapp.token'))
                ->timeout(10)
                ->post($this->endpoint(), [
                    'messaging_product' => 'whatsapp',
                    'to' => ltrim($to, '+'),
                    'type' => 'text',
                    'text' => ['body' => $text],
                ]);

            NotificationLog::create([
                'booking_id' => $bookingId,
                'channel' => 'whatsapp',
                'template' => 'respuesta',
                'recipient' => $to,
                'status' => $response->successful() ? 'sent' : 'failed',
                'provider_id' => $response->json('messages.0.id'),
                'error' => $response->successful() ? null : mb_substr((string) ($response->json('error.message') ?? $response->body()), 0, 1000),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function endpoint(): string
    {
        return sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            config('services.whatsapp.api_version'),
            config('services.whatsapp.phone_number_id'),
        );
    }

    private function log(WhatsAppMessage $message, string $to, string $status, ?string $providerId = null, ?string $error = null): void
    {
        NotificationLog::create([
            'booking_id' => $message->bookingId,
            'channel' => 'whatsapp',
            'template' => $message->template,
            'recipient' => $to,
            'status' => $status,
            'provider_id' => $providerId,
            'error' => $error ? mb_substr($error, 0, 1000) : null,
        ]);
    }
}
