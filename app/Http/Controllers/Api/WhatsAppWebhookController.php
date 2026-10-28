<?php

namespace App\Http\Controllers\Api;

use App\Actions\Bookings\ChangeBookingStatus;
use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\NotificationLog;
use App\Services\WhatsApp;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Meta WhatsApp webhook: reminder button taps ("Confirmo asistencia" / "Cancelar reserva")
 * and delivery receipts for the message log.
 */
class WhatsAppWebhookController extends Controller
{
    public function __construct(private WhatsApp $whatsApp, private ChangeBookingStatus $changeStatus) {}

    /**
     * Subscription handshake when the webhook is configured in Meta's dashboard.
     */
    public function verify(Request $request): Response
    {
        $token = config('services.whatsapp.verify_token');

        abort_unless(
            filled($token) && $request->query('hub_mode') === 'subscribe' && hash_equals($token, (string) $request->query('hub_verify_token')),
            403,
        );

        return response((string) $request->query('hub_challenge'));
    }

    public function handle(Request $request): Response
    {
        $secret = config('services.whatsapp.app_secret');
        $signature = 'sha256='.hash_hmac('sha256', $request->getContent(), (string) $secret);

        abort_unless(filled($secret) && hash_equals($signature, (string) $request->header('X-Hub-Signature-256')), 403);

        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                foreach ($change['value']['messages'] ?? [] as $message) {
                    $this->handleMessage($message);
                }

                foreach ($change['value']['statuses'] ?? [] as $status) {
                    $this->handleStatus($status);
                }
            }
        }

        // Always 200 so Meta doesn't retry events we chose to ignore.
        return response('ok');
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function handleMessage(array $message): void
    {
        $payload = $message['button']['payload'] ?? $message['interactive']['button_reply']['id'] ?? null;

        if (! is_string($payload) || ! str_contains($payload, ':')) {
            return;
        }

        [$action, $token] = explode(':', $payload, 2);
        $booking = Booking::with(['space', 'customer'])->where('manage_token', $token)->first();
        $from = '+'.ltrim((string) ($message['from'] ?? ''), '+');

        // Only the booking's own customer can act on it.
        if (! $booking || $booking->customer->phone !== $from) {
            return;
        }

        match ($action) {
            'CONFIRM' => $this->confirmAttendance($booking, $from),
            'CANCEL' => $this->cancel($booking, $from),
            default => null,
        };
    }

    private function confirmAttendance(Booking $booking, string $from): void
    {
        if (! in_array($booking->status, BookingStatus::holdingSlot(), true)) {
            $this->whatsApp->reply($from, "Tu reserva {$booking->code} ya no está activa. Si quieres, puedes reservar de nuevo en ".route('bookings.create').' .', $booking->id);

            return;
        }

        if ($booking->attendance_confirmed_at === null) {
            $booking->update(['attendance_confirmed_at' => now()]);
            $booking->events()->create(['from_status' => $booking->status, 'to_status' => $booking->status, 'note' => 'Confirmó asistencia por WhatsApp.']);
        }

        $this->whatsApp->reply($from, "¡Gracias, {$booking->customer->name}! Te esperamos el {$booking->scheduleLabel()}.", $booking->id);
    }

    private function cancel(Booking $booking, string $from): void
    {
        if (! $booking->canBeCancelledByCustomer()) {
            $this->whatsApp->reply($from, "Ya no es posible cancelar la reserva {$booking->code} en línea. Escríbenos por aquí y te ayudamos.", $booking->id);

            return;
        }

        try {
            $this->changeStatus->handle($booking, BookingStatus::Cancelled, reason: 'Cancelada por el cliente (WhatsApp).', byCustomer: true);
        } catch (DomainException) {
            return;
        }

        $this->whatsApp->reply($from, "Listo, cancelamos tu reserva {$booking->code}. ¡Gracias por avisarnos!", $booking->id);
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function handleStatus(array $status): void
    {
        $state = $status['status'] ?? null;

        if (! in_array($state, ['delivered', 'read', 'failed'], true) || empty($status['id'])) {
            return;
        }

        NotificationLog::where('provider_id', $status['id'])->update([
            'status' => $state === 'failed' ? 'failed' : $state,
            'error' => $state === 'failed' ? mb_substr((string) ($status['errors'][0]['title'] ?? 'Error de entrega'), 0, 1000) : null,
        ]);
    }
}
