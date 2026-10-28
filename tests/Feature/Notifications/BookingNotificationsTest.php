<?php

namespace Tests\Feature\Notifications;

use App\Actions\Bookings\ChangeBookingStatus;
use App\Actions\Bookings\CreateBooking;
use App\Actions\Bookings\RescheduleBooking;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\NotificationLog;
use App\Models\OpeningHour;
use App\Models\Setting;
use App\Models\Space;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Notifications\BookingCancelled;
use App\Notifications\BookingConfirmed;
use App\Notifications\BookingReminder;
use App\Notifications\BookingRequested;
use App\Notifications\BookingRescheduled;
use App\Notifications\StaffBookingCancelled;
use App\Notifications\StaffNewBooking;
use App\Notifications\WaitlistSpotAvailable;
use App\Support\Ics;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BookingNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $start;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-02 08:00')); // Monday
        $this->start = CarbonImmutable::parse('2026-11-04 10:00');

        foreach ([1, 2, 3, 4, 5] as $weekday) {
            OpeningHour::create(['weekday' => $weekday, 'opens_at' => '08:00', 'closes_at' => '19:00']);
        }

        Setting::put(['notify_emails' => ['equipo@deal.test'], 'venue_address' => 'Calle Larga 1-23, Cuenca']);
    }

    private function book(Space $space, BookingSource $source = BookingSource::Web, array $customer = []): Booking
    {
        return app(CreateBooking::class)->handle(
            $space,
            $this->start,
            $this->start->addHours(2),
            $customer + ['name' => 'Ana Pérez', 'email' => 'ana@example.com', 'phone' => '+593991234567', 'whatsapp_opt_in' => true],
            source: $source,
        );
    }

    public function test_web_request_notifies_customer_and_team(): void
    {
        Notification::fake();
        User::factory()->create();

        $booking = $this->book(Space::factory()->create(['requires_approval' => true]));

        Notification::assertSentTo($booking->customer, BookingRequested::class);
        Notification::assertSentOnDemand(StaffNewBooking::class, fn ($notification, $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === ['equipo@deal.test']);
    }

    public function test_instant_booking_sends_the_confirmation(): void
    {
        Notification::fake();

        $booking = $this->book(Space::factory()->autoConfirm()->create());

        Notification::assertSentTo($booking->customer, BookingConfirmed::class);
        Notification::assertNotSentTo($booking->customer, BookingRequested::class);
    }

    public function test_staff_bookings_do_not_alert_the_team(): void
    {
        Notification::fake();

        $booking = $this->book(Space::factory()->create(), BookingSource::Admin);

        Notification::assertSentTo($booking->customer, BookingConfirmed::class);
        Notification::assertSentOnDemandTimes(StaffNewBooking::class, 0);
    }

    public function test_confirming_and_customer_cancelling_notify_the_right_people(): void
    {
        Notification::fake();
        $booking = $this->book(Space::factory()->create());

        app(ChangeBookingStatus::class)->handle($booking, BookingStatus::Confirmed, User::factory()->create());
        Notification::assertSentTo($booking->customer, BookingConfirmed::class);

        app(ChangeBookingStatus::class)->handle($booking->fresh(), BookingStatus::Cancelled, byCustomer: true);
        Notification::assertSentTo($booking->customer, BookingCancelled::class, fn (BookingCancelled $n) => $n->byCustomer);
        Notification::assertSentOnDemand(StaffBookingCancelled::class);
    }

    public function test_rescheduling_notifies_the_customer(): void
    {
        Notification::fake();
        $booking = $this->book(Space::factory()->autoConfirm()->create());

        app(RescheduleBooking::class)->handle($booking, $this->start->addDay(), $this->start->addDay()->addHours(2), byCustomer: true);

        Notification::assertSentTo($booking->customer, BookingRescheduled::class);
        $this->assertSame('2026-11-05 10:00', $booking->fresh()->starts_at->format('Y-m-d H:i'));
    }

    public function test_a_freed_slot_alerts_the_waitlist_once(): void
    {
        Notification::fake();
        $space = Space::factory()->autoConfirm()->create();
        $booking = $this->book($space);
        $entry = WaitlistEntry::factory()->for($space)->create(['date' => '2026-11-04']);

        app(ChangeBookingStatus::class)->handle($booking, BookingStatus::Cancelled, User::factory()->create());

        Notification::assertSentTo($entry, WaitlistSpotAvailable::class);
        $this->assertNotNull($entry->fresh()->notified_at);
    }

    public function test_emails_render_with_booking_details_and_calendar_file(): void
    {
        $booking = $this->book(Space::factory()->autoConfirm()->create(['name' => 'Sala Andes']));

        $mail = (new BookingConfirmed($booking))->toMail($booking->customer);
        $html = (string) $mail->render();

        $this->assertStringContainsString('Sala Andes', $html);
        $this->assertStringContainsString($booking->code, $html);
        $this->assertStringContainsString('Calle Larga 1-23', $html);
        $this->assertStringContainsString($booking->manageUrl(), $html);
        $this->assertSame("reserva-{$booking->code}.ics", $mail->rawAttachments[0]['name']);

        $this->assertNotEmpty(NotificationLog::where('booking_id', $booking->id)->where('channel', 'mail')->get());
    }

    public function test_calendar_file_is_valid_ical(): void
    {
        $booking = Booking::factory()->create(['starts_at' => $this->start, 'ends_at' => $this->start->addHours(2)]);

        $ics = Ics::booking($booking);

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString('DTSTART:20261104T150000Z', $ics); // 10:00 Guayaquil = 15:00 UTC
        $this->assertStringContainsString("UID:booking-{$booking->id}@", $ics);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);
    }

    public function test_whatsapp_is_sent_only_when_configured_and_opted_in(): void
    {
        config(['services.whatsapp.token' => 'test-token', 'services.whatsapp.phone_number_id' => '123']);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.ABC']]])]);

        $booking = $this->book(Space::factory()->autoConfirm()->create());
        app(CreateBooking::class)->handle(
            Space::factory()->autoConfirm()->create(),
            $this->start,
            $this->start->addHours(2),
            ['name' => 'Luis', 'email' => 'luis@example.com', 'phone' => '+593987654321', 'whatsapp_opt_in' => false],
        );

        Http::assertSent(fn ($request) => $request['to'] === '593991234567'
            && $request['template']['name'] === 'reserva_confirmada'
            && $request['template']['components'][0]['parameters'][1]['text'] === $booking->code);
        Http::assertNotSent(fn ($request) => $request['to'] === '593987654321');

        $this->assertSame('wamid.ABC', NotificationLog::where('channel', 'whatsapp')->value('provider_id'));
    }

    public function test_whatsapp_failures_are_logged_without_breaking_the_booking(): void
    {
        config(['services.whatsapp.token' => 'test-token', 'services.whatsapp.phone_number_id' => '123']);
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Template not approved']], 400)]);

        $booking = $this->book(Space::factory()->autoConfirm()->create());

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame('Template not approved', NotificationLog::where('channel', 'whatsapp')->value('error'));
    }

    public function test_reminder_carries_confirm_and_cancel_buttons(): void
    {
        $booking = Booking::factory()->create();

        $payload = (new BookingReminder($booking))->toWhatsapp($booking->customer)->toPayload('+593991234567');

        $this->assertSame('recordatorio_reserva', $payload['template']['name']);
        $this->assertSame("CONFIRM:{$booking->manage_token}", $payload['template']['components'][1]['parameters'][0]['payload']);
        $this->assertSame("CANCEL:{$booking->manage_token}", $payload['template']['components'][2]['parameters'][0]['payload']);
    }

    public function test_repeat_no_shows_require_approval(): void
    {
        Notification::fake();
        Customer::factory()->create(['email' => 'ana@example.com', 'no_show_count' => 3]);

        $booking = $this->book(Space::factory()->autoConfirm()->create());

        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertStringContainsString('inasistencias', $booking->status_reason);
    }
}
