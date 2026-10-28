<?php

namespace Tests\Feature\Bookings;

use App\Enums\BookingStatus;
use App\Livewire\BookingForm;
use App\Livewire\RescheduleForm;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\OpeningHour;
use App\Models\Space;
use App\Models\WaitlistEntry;
use App\Notifications\CustomerBookingsLink;
use App\Services\Availability;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class PublicExperienceTest extends TestCase
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

    private function fullDay(Space $space, string $date, int $attendees = 1): Booking
    {
        return Booking::factory()->for($space)->create([
            'starts_at' => CarbonImmutable::parse("{$date} 08:00"),
            'ends_at' => CarbonImmutable::parse("{$date} 19:00"),
            'attendees' => $attendees,
        ]);
    }

    public function test_day_strip_shows_closed_full_and_available_days(): void
    {
        $space = Space::factory()->daily()->create(['slug' => 'oficina']);
        $this->fullDay($space, '2026-11-04');

        $statuses = Livewire::test(BookingForm::class)->set('spaceSlug', 'oficina')->instance()->dayStatuses;

        $this->assertSame(Availability::DAY_AVAILABLE, $statuses['2026-11-03']);
        $this->assertSame(Availability::DAY_FULL, $statuses['2026-11-04']);
        $this->assertSame(Availability::DAY_CLOSED, $statuses['2026-11-08']); // Sunday
        $this->assertCount(14, $statuses);
    }

    public function test_taken_times_are_shown_disabled(): void
    {
        $space = Space::factory()->create(['slug' => 'sala']);
        Booking::factory()->for($space)->create(['starts_at' => CarbonImmutable::parse('2026-11-04 10:00'), 'ends_at' => CarbonImmutable::parse('2026-11-04 11:00')]);

        $slots = collect(Livewire::test(BookingForm::class)->set('spaceSlug', 'sala')->call('pickDay', '2026-11-04')->instance()->timeSlots)
            ->mapWithKeys(fn ($slot) => [$slot['start']->format('H:i') => $slot['available']]);

        $this->assertFalse($slots['10:00']);
        $this->assertTrue($slots['11:00']);
    }

    public function test_full_space_suggests_alternatives_and_waitlist(): void
    {
        $full = Space::factory()->daily()->create(['slug' => 'oficina-a', 'name' => 'Oficina A']);
        Space::factory()->daily()->create(['slug' => 'oficina-b', 'name' => 'Oficina B']);
        $this->fullDay($full, '2026-11-04');

        Livewire::test(BookingForm::class)
            ->set('spaceSlug', 'oficina-a')
            ->call('pickDay', '2026-11-04')
            ->assertSee('Disponible ese día en:')
            ->assertSee('Oficina B')
            ->assertSee('¿Te avisamos si se libera?')
            ->set('name', 'Ana')
            ->set('email', 'ana@example.com')
            ->call('joinWaitlist')
            ->assertSee('Te escribiremos si se libera')
            ->call('switchSpace', 'oficina-b')
            ->assertSet('spaceSlug', 'oficina-b')
            ->assertSet('date', '2026-11-04');

        $this->assertSame('2026-11-04', WaitlistEntry::sole()->date->toDateString());
    }

    public function test_visitor_books_a_half_day_with_estimated_price(): void
    {
        Space::factory()->daily()->create(['slug' => 'oficina', 'allows_half_day' => true, 'price' => 40, 'half_day_price' => 25]);

        Livewire::test(BookingForm::class)
            ->set('spaceSlug', 'oficina')
            ->call('pickDay', '2026-11-04')
            ->set('period', 'am')
            ->assertSee('Total estimado')
            ->assertSee('$25.00')
            ->set('name', 'Ana Pérez')
            ->set('email', 'ana@example.com')
            ->set('countryCode', '1')
            ->set('phone', '(305) 555-0134')
            ->set('privacy', true)
            ->call('book')
            ->assertHasNoErrors();

        $booking = Booking::sole();
        $this->assertSame('08:00', $booking->starts_at->format('H:i'));
        $this->assertSame('13:00', $booking->ends_at->format('H:i'));
        $this->assertSame('+13055550134', $booking->customer->phone);
        $this->assertFalse($booking->customer->whatsapp_opt_in);
    }

    public function test_links_can_preselect_day_and_time(): void
    {
        Space::factory()->create(['slug' => 'sala']);

        Livewire::withQueryParams(['espacio' => 'sala', 'fecha' => '2026-11-04', 'hora' => '15:00'])
            ->test(BookingForm::class)
            ->assertSet('date', '2026-11-04')
            ->assertSet('time', '15:00');
    }

    public function test_customer_reschedules_from_their_page(): void
    {
        Notification::fake();
        $booking = Booking::factory()->create([
            'space_id' => Space::factory()->autoConfirm()->create()->id,
            'starts_at' => CarbonImmutable::parse('2026-11-04 10:00'),
            'ends_at' => CarbonImmutable::parse('2026-11-04 12:00'),
        ]);

        $this->get(route('bookings.show', $booking->manage_token))->assertSee('Cambiar horario');
        $this->get(route('bookings.reschedule', $booking->manage_token))->assertOk();

        Livewire::test(RescheduleForm::class, ['token' => $booking->manage_token])
            ->assertSet('duration', 120)
            ->call('pickDay', '2026-11-05')
            ->set('time', '14:00')
            ->call('save')
            ->assertRedirect(route('bookings.show', $booking->manage_token));

        $booking->refresh();
        $this->assertSame('2026-11-05 14:00', $booking->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-11-05 16:00', $booking->ends_at->format('Y-m-d H:i'));
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
    }

    public function test_manage_page_offers_calendar_map_and_check_in_code(): void
    {
        $booking = Booking::factory()->create(['starts_at' => CarbonImmutable::parse('2026-11-04 10:00'), 'ends_at' => CarbonImmutable::parse('2026-11-04 12:00')]);

        $this->get(route('bookings.show', $booking->manage_token))
            ->assertOk()
            ->assertSee('Cómo llegar')
            ->assertSee('Muestra este código en recepción')
            ->assertSee('<svg', false);

        $this->get(route('bookings.calendar', $booking->manage_token))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertSee('BEGIN:VCALENDAR', false);
    }

    public function test_lost_link_email_never_reveals_whether_a_customer_exists(): void
    {
        Notification::fake();
        $customer = Customer::factory()->create(['email' => 'ana@example.com']);
        Booking::factory()->for($customer)->create();

        $this->post(route('bookings.lookup.send'), ['email' => 'ANA@example.com'])->assertRedirect()->assertSessionHas('sent');
        $this->post(route('bookings.lookup.send'), ['email' => 'nadie@example.com'])->assertRedirect()->assertSessionHas('sent');

        Notification::assertSentTo($customer, CustomerBookingsLink::class);
        Notification::assertSentTimes(CustomerBookingsLink::class, 1);
    }

    public function test_public_pages_render(): void
    {
        $space = Space::factory()->create(['name' => 'Sala Andes']);

        foreach (['/', '/contacto', '/privacidad', '/condiciones', '/mis-reservas', '/reservar'] as $page) {
            $this->get($page)->assertOk();
        }

        $this->get('/privacidad')->assertSee('Superintendencia de Protección de Datos Personales');
        $this->get('/')->assertSee('Libre hoy');
        $this->get(route('spaces.door-sign', $space))->assertOk()->assertSee('Escanea para reservar este espacio');
        $this->get('/no-existe')->assertNotFound()->assertSee('No encontramos esta página');
    }

    public function test_phone_numbers_respect_the_country_code(): void
    {
        $this->assertSame('+593991234567', PhoneNumber::normalize('099 123 4567'));
        $this->assertSame('+13055550134', PhoneNumber::normalize('(305) 555-0134', '1'));
        $this->assertSame('+573001234567', PhoneNumber::normalize('300 123 4567', '57'));
        $this->assertSame('+34612345678', PhoneNumber::normalize('+34 612 34 56 78', '593'));
        $this->assertNull(PhoneNumber::normalize('12', '1'));
    }
}
