<?php

namespace Tests\Feature\Bookings;

use App\Actions\Bookings\ChangeBookingStatus;
use App\Actions\Bookings\CreateBooking;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Exceptions\BookingUnavailable;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\OpeningHour;
use App\Models\Space;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingActionsTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $start;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-02 08:00')); // Monday
        $this->start = CarbonImmutable::parse('2026-11-04 10:00');  // Wednesday

        foreach ([1, 2, 3, 4, 5] as $weekday) {
            OpeningHour::create(['weekday' => $weekday, 'opens_at' => '08:00', 'closes_at' => '19:00']);
        }
    }

    private function book(Space $space, array $customer = [], BookingSource $source = BookingSource::Web): Booking
    {
        return app(CreateBooking::class)->handle(
            $space,
            $this->start,
            $this->start->addHours(2),
            $customer + ['name' => 'Ana Pérez', 'email' => 'ana@example.com', 'phone' => '+593991234567'],
            source: $source,
        );
    }

    public function test_web_booking_needs_approval_when_the_space_requires_it(): void
    {
        $booking = $this->book(Space::factory()->create(['requires_approval' => true]));

        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertNull($booking->confirmed_at);
        $this->assertMatchesRegularExpression('/^DL-[A-Z2-9]{6}$/', $booking->code);
        $this->assertSame(64, strlen($booking->manage_token));
        $this->assertSame(BookingStatus::Pending, $booking->events()->sole()->to_status);
    }

    public function test_auto_confirm_spaces_and_staff_bookings_are_confirmed_immediately(): void
    {
        $this->assertSame(BookingStatus::Confirmed, $this->book(Space::factory()->autoConfirm()->create())->status);
        $this->assertSame(BookingStatus::Confirmed, $this->book(Space::factory()->create(), source: BookingSource::Admin)->status);
    }

    public function test_returning_customers_are_matched_by_email(): void
    {
        $this->book(Space::factory()->create(), ['email' => 'Ana@Example.com ', 'whatsapp_opt_in' => true]);
        $this->book(Space::factory()->create(), ['name' => 'Ana P. Pérez']);

        $customer = Customer::sole();
        $this->assertSame('ana@example.com', $customer->email);
        $this->assertSame('Ana P. Pérez', $customer->name);
        $this->assertCount(2, $customer->bookings);
        $this->assertNotNull($customer->privacy_accepted_at);
    }

    public function test_cannot_double_book_a_slot(): void
    {
        $space = Space::factory()->create();
        $this->book($space);

        $this->expectException(BookingUnavailable::class);

        $this->book($space, ['email' => 'otro@example.com']);
    }

    public function test_status_changes_are_validated_and_logged(): void
    {
        $user = User::factory()->create();
        $booking = $this->book(Space::factory()->create());
        $change = app(ChangeBookingStatus::class);

        $booking = $change->handle($booking, BookingStatus::Confirmed, $user);
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertNotNull($booking->confirmed_at);

        $booking = $change->handle($booking, BookingStatus::Cancelled, $user, 'Cliente llamó para cancelar');
        $this->assertNotNull($booking->cancelled_at);
        $this->assertSame('Cliente llamó para cancelar', $booking->status_reason);
        $this->assertCount(3, $booking->events);

        $this->expectException(DomainException::class);
        $change->handle($booking, BookingStatus::Confirmed, $user);
    }

    public function test_no_show_is_counted_on_the_customer(): void
    {
        $booking = $this->book(Space::factory()->autoConfirm()->create());

        app(ChangeBookingStatus::class)->handle($booking, BookingStatus::NoShow);

        $this->assertSame(1, $booking->customer->fresh()->no_show_count);
    }
}
