<?php

namespace Tests\Feature\Api;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\NotificationLog;
use App\Models\OpeningHour;
use App\Models\Space;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiAndWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-02 08:00'));

        foreach ([1, 2, 3, 4, 5] as $weekday) {
            OpeningHour::create(['weekday' => $weekday, 'opens_at' => '08:00', 'closes_at' => '19:00']);
        }

        config([
            'services.whatsapp.token' => 'token',
            'services.whatsapp.phone_number_id' => '123',
            'services.whatsapp.app_secret' => 'app-secret',
            'services.whatsapp.verify_token' => 'verify-me',
        ]);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.reply']]])]);
    }

    public function test_api_lists_spaces_and_availability(): void
    {
        Space::factory()->create(['slug' => 'sala', 'name' => 'Sala Andes']);
        Space::factory()->create(['slug' => 'oculta', 'is_bookable' => false]);

        $this->getJson('/api/v1/spaces')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'sala');

        $this->getJson('/api/v1/spaces/sala/availability')->assertOk()->assertJsonPath('data.days.2026-11-08', 'closed');

        $this->getJson('/api/v1/spaces/sala/availability?date=2026-11-04&duration=60')
            ->assertOk()
            ->assertJsonPath('data.slots.0', ['start' => '08:00', 'available' => true]);

        $this->getJson('/api/v1/spaces/oculta')->assertNotFound();
    }

    public function test_webhook_verification_handshake(): void
    {
        $this->get('/api/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=42')->assertOk()->assertSee('42');
        $this->get('/api/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=42')->assertForbidden();
    }

    private function postWebhook(array $payload, ?string $secret = 'app-secret')
    {
        $body = json_encode($payload);

        return $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, (string) $secret),
        ], $body);
    }

    private function buttonTap(Booking $booking, string $action, string $from = '593991234567'): array
    {
        return ['entry' => [['changes' => [['value' => ['messages' => [[
            'from' => $from,
            'type' => 'button',
            'button' => ['payload' => "{$action}:{$booking->manage_token}", 'text' => 'x'],
        ]]]]]]]];
    }

    private function booking(): Booking
    {
        return Booking::factory()->for(Customer::factory()->create(['phone' => '+593991234567', 'whatsapp_opt_in' => true]))->create([
            'starts_at' => CarbonImmutable::parse('2026-11-04 10:00'),
            'ends_at' => CarbonImmutable::parse('2026-11-04 12:00'),
        ]);
    }

    public function test_reminder_button_confirms_attendance(): void
    {
        $booking = $this->booking();

        $this->postWebhook($this->buttonTap($booking, 'CONFIRM'))->assertOk();

        $this->assertNotNull($booking->fresh()->attendance_confirmed_at);
        Http::assertSent(fn ($request) => $request['type'] === 'text' && str_contains($request['text']['body'], 'Te esperamos'));
    }

    public function test_reminder_button_cancels_the_booking(): void
    {
        $booking = $this->booking();

        $this->postWebhook($this->buttonTap($booking, 'CANCEL'))->assertOk();

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
    }

    public function test_buttons_only_work_from_the_customers_own_number(): void
    {
        $booking = $this->booking();

        $this->postWebhook($this->buttonTap($booking, 'CANCEL', '593900000000'))->assertOk();

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_unsigned_webhooks_are_rejected(): void
    {
        $booking = $this->booking();

        $this->postWebhook($this->buttonTap($booking, 'CANCEL'), secret: 'wrong')->assertForbidden();

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_delivery_receipts_update_the_message_log(): void
    {
        $log = NotificationLog::create(['channel' => 'whatsapp', 'template' => 'reserva_confirmada', 'recipient' => '+593991234567', 'status' => 'sent', 'provider_id' => 'wamid.X']);

        $this->postWebhook(['entry' => [['changes' => [['value' => ['statuses' => [['id' => 'wamid.X', 'status' => 'read']]]]]]]])->assertOk();

        $this->assertSame('read', $log->fresh()->status);
    }
}
