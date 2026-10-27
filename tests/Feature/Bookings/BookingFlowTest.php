<?php

namespace Tests\Feature\Bookings;

use App\Enums\BookingStatus;
use App\Livewire\BookingForm;
use App\Models\Booking;
use App\Models\OpeningHour;
use App\Models\Space;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-02 08:00')); // Monday

        foreach ([1, 2, 3, 4, 5] as $weekday) {
            OpeningHour::create(['weekday' => $weekday, 'opens_at' => '08:00', 'closes_at' => '19:00']);
        }
    }

    private function fillDetails($component)
    {
        return $component
            ->set('name', 'Ana Pérez')
            ->set('email', 'ana@example.com')
            ->set('phone', '099 123 4567')
            ->set('privacy', true);
    }

    public function test_booking_page_lists_bookable_spaces(): void
    {
        Space::factory()->create(['name' => 'Sala Andes']);
        Space::factory()->create(['name' => 'Sala Oculta', 'is_bookable' => false]);

        $this->get(route('bookings.create'))
            ->assertOk()
            ->assertSee('Sala Andes')
            ->assertDontSee('Sala Oculta');
    }

    public function test_visitor_books_an_hourly_space(): void
    {
        $space = Space::factory()->create(['slug' => 'sala-andes']);

        $component = Livewire::test(BookingForm::class)
            ->set('spaceSlug', 'sala-andes')
            ->assertSet('duration', 60)
            ->set('date', '2026-11-04')
            ->set('duration', 120)
            ->assertSee('10:00')
            ->set('time', '10:00');

        $this->fillDetails($component)
            ->call('book')
            ->assertHasNoErrors()
            ->assertRedirect(route('bookings.show', Booking::sole()->manage_token));

        $booking = Booking::sole();
        $this->assertTrue($booking->space->is($space));
        $this->assertSame('2026-11-04 10:00', $booking->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-11-04 12:00', $booking->ends_at->format('Y-m-d H:i'));
        $this->assertSame('+593991234567', $booking->customer->phone);
        $this->assertTrue($booking->customer->whatsapp_opt_in);
    }

    public function test_visitor_books_a_full_day_space(): void
    {
        Space::factory()->capacity(10)->create(['slug' => 'coworking']);

        $component = Livewire::test(BookingForm::class)
            ->set('spaceSlug', 'coworking')
            ->set('date', '2026-11-04')
            ->set('attendees', 2)
            ->assertSee('Hay disponibilidad');

        $this->fillDetails($component)->call('book')->assertHasNoErrors();

        $booking = Booking::sole();
        $this->assertSame('08:00', $booking->starts_at->format('H:i'));
        $this->assertSame('19:00', $booking->ends_at->format('H:i'));
        $this->assertSame(2, $booking->attendees);
    }

    public function test_details_are_validated(): void
    {
        Space::factory()->create(['slug' => 'sala']);

        Livewire::test(BookingForm::class)
            ->set('spaceSlug', 'sala')
            ->set('date', '2026-11-04')
            ->set('time', '10:00')
            ->set('email', 'no-es-correo')
            ->set('phone', '123')
            ->call('book')
            ->assertHasErrors(['name' => 'required', 'email' => 'email', 'phone' => 'regex', 'privacy' => 'accepted']);

        $this->assertSame(0, Booking::count());
    }

    public function test_a_taken_slot_shows_an_error_instead_of_booking(): void
    {
        $space = Space::factory()->create(['slug' => 'sala']);

        $component = Livewire::test(BookingForm::class)
            ->set('spaceSlug', 'sala')
            ->set('date', '2026-11-04')
            ->set('time', '10:00');

        Booking::factory()->for($space)->create([
            'starts_at' => CarbonImmutable::parse('2026-11-04 10:00'),
            'ends_at' => CarbonImmutable::parse('2026-11-04 11:00'),
        ]);

        $this->fillDetails($component)
            ->call('book')
            ->assertHasErrors(['time'])
            ->assertSet('time', null);

        $this->assertSame(1, Booking::count());
    }

    public function test_honeypot_silently_drops_bots(): void
    {
        Space::factory()->create(['slug' => 'sala']);

        $component = Livewire::test(BookingForm::class)
            ->set('spaceSlug', 'sala')
            ->set('date', '2026-11-04')
            ->set('time', '10:00')
            ->set('website', 'http://spam.example');

        $this->fillDetails($component)->call('book')->assertNoRedirect();

        $this->assertSame(0, Booking::count());
    }

    public function test_customer_sees_and_cancels_their_booking(): void
    {
        $booking = Booking::factory()->status(BookingStatus::Pending)->create([
            'starts_at' => CarbonImmutable::parse('2026-11-04 10:00'),
            'ends_at' => CarbonImmutable::parse('2026-11-04 12:00'),
        ]);

        $this->get(route('bookings.show', $booking->manage_token))
            ->assertOk()
            ->assertSee($booking->code)
            ->assertSee('Cancelar reserva');

        $this->post(route('bookings.cancel', $booking->manage_token))
            ->assertRedirect(route('bookings.show', $booking->manage_token));

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
    }

    public function test_cancellation_is_closed_inside_the_window(): void
    {
        $booking = Booking::factory()->create([
            'starts_at' => CarbonImmutable::parse('2026-11-02 18:00'), // 10 h from now, window is 24 h
            'ends_at' => CarbonImmutable::parse('2026-11-02 19:00'),
        ]);

        $this->get(route('bookings.show', $booking->manage_token))->assertDontSee('Cancelar reserva');
        $this->post(route('bookings.cancel', $booking->manage_token))->assertForbidden();
    }

    public function test_unknown_tokens_are_not_found(): void
    {
        $this->get(route('bookings.show', str_repeat('x', 64)))->assertNotFound();
    }

    public function test_phone_numbers_are_normalized(): void
    {
        $this->assertSame('+593991234567', PhoneNumber::normalize('099 123 4567'));
        $this->assertSame('+593991234567', PhoneNumber::normalize('991234567'));
        $this->assertSame('+593991234567', PhoneNumber::normalize('+593 99 123 4567'));
        $this->assertSame('+573001234567', PhoneNumber::normalize('+57 300 123 4567'));
        $this->assertNull(PhoneNumber::normalize('123'));
    }
}
