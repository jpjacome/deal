<?php

namespace Tests\Feature\Admin;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Filament\Pages\Agenda;
use App\Filament\Resources\BlockedPeriods\Pages\ManageBlockedPeriods;
use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Spaces\Pages\CreateSpace;
use App\Models\BlockedPeriod;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\OpeningHour;
use App\Models\Space;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-02 08:00')); // Monday

        foreach ([1, 2, 3, 4, 5] as $weekday) {
            OpeningHour::create(['weekday' => $weekday, 'opens_at' => '08:00', 'closes_at' => '19:00']);
        }

        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($this->admin);
    }

    public function test_every_admin_page_renders(): void
    {
        $booking = Booking::factory()->create();
        BlockedPeriod::factory()->create();

        $pages = [
            '/admin',
            '/admin/agenda',
            '/admin/bookings',
            '/admin/bookings?tab=pending',
            '/admin/bookings/create',
            "/admin/bookings/{$booking->id}",
            '/admin/spaces',
            '/admin/spaces/create',
            "/admin/spaces/{$booking->space_id}/edit",
            '/admin/customers',
            "/admin/customers/{$booking->customer_id}/edit",
            '/admin/opening-hours',
            '/admin/blocked-periods',
            '/admin/users',
            '/admin/settings',
            '/admin/reports',
            '/admin/reports?dias=365',
            '/admin/waitlist-entries',
            '/admin/profile',
            '/admin/agenda?vista=semana',
        ];

        foreach ($pages as $page) {
            $this->get($page)->assertOk();
        }
    }

    public function test_staff_cannot_manage_the_team(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]));

        $this->get('/admin/bookings')->assertOk();
        $this->get('/admin/users')->assertForbidden();
    }

    public function test_bookings_list_shows_upcoming_bookings(): void
    {
        $booking = Booking::factory()->create();
        $past = Booking::factory()->create([
            'starts_at' => CarbonImmutable::parse('2026-10-20 10:00'),
            'ends_at' => CarbonImmutable::parse('2026-10-20 11:00'),
        ]);

        Livewire::test(ListBookings::class)
            ->assertCanSeeTableRecords([$booking])
            ->assertCanNotSeeTableRecords([$past]);
    }

    public function test_staff_confirms_a_pending_booking(): void
    {
        $booking = Booking::factory()->status(BookingStatus::Pending)->create();

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionVisible('confirm')
            ->assertActionHidden('complete')
            ->callAction('confirm')
            ->assertNotified();

        $booking->refresh();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame($this->admin->id, $booking->events()->first()->user_id);
    }

    public function test_staff_rejects_a_booking_with_a_reason(): void
    {
        $booking = Booking::factory()->status(BookingStatus::Pending)->create();

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('reject', data: ['reason' => 'Sala en mantenimiento']);

        $booking->refresh();
        $this->assertSame(BookingStatus::Rejected, $booking->status);
        $this->assertSame('Sala en mantenimiento', $booking->status_reason);
    }

    public function test_staff_creates_a_booking_for_a_phone_customer(): void
    {
        $space = Space::factory()->create();

        Livewire::test(CreateBooking::class)
            ->fillForm([
                'space_id' => $space->id,
                'date' => '2026-11-04',
                'start_time' => '10:00',
                'end_time' => '12:00',
                'attendees' => 2,
                'email' => 'luis@example.com',
                'name' => 'Luis Mora',
                'phone' => '0987654321',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $booking = Booking::sole();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(BookingSource::Admin, $booking->source);
        $this->assertSame('+593987654321', $booking->customer->phone);
        $this->assertSame('2026-11-04 12:00', $booking->ends_at->format('Y-m-d H:i'));
    }

    public function test_staff_booking_is_refused_when_the_slot_is_taken(): void
    {
        $space = Space::factory()->create();
        Booking::factory()->for($space)->create([
            'starts_at' => CarbonImmutable::parse('2026-11-04 10:00'),
            'ends_at' => CarbonImmutable::parse('2026-11-04 12:00'),
        ]);

        Livewire::test(CreateBooking::class)
            ->fillForm([
                'space_id' => $space->id,
                'date' => '2026-11-04',
                'start_time' => '11:00',
                'end_time' => '13:00',
                'attendees' => 1,
                'email' => 'luis@example.com',
                'name' => 'Luis Mora',
            ])
            ->call('create')
            ->assertNotified('No se pudo crear la reserva');

        $this->assertSame(1, Booking::count());
    }

    public function test_staff_creates_a_space(): void
    {
        Livewire::test(CreateSpace::class)
            ->fillForm([
                'name' => 'Sala Cajas',
                'slug' => 'sala-cajas',
                'capacity' => 10,
                'booking_mode' => 'hourly',
                'slot_minutes' => 30,
                'min_minutes' => 60,
                'max_minutes' => 240,
                'buffer_minutes' => 15,
                'lead_time_minutes' => 60,
                'horizon_days' => 30,
                'cancellation_hours' => 12,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $space = Space::sole();
        $this->assertSame(30, $space->slot_minutes);
        $this->assertTrue($space->requires_approval);
    }

    public function test_blocking_a_period_warns_about_affected_bookings(): void
    {
        $booking = Booking::factory()->create([
            'starts_at' => CarbonImmutable::parse('2026-11-04 10:00'),
            'ends_at' => CarbonImmutable::parse('2026-11-04 12:00'),
        ]);

        Livewire::test(ManageBlockedPeriods::class)
            ->callAction('create', data: [
                'starts_at' => '2026-11-04 08:00',
                'ends_at' => '2026-11-04 19:00',
                'reason' => 'Feriado',
            ])
            ->assertNotified('Hay reservas activas en ese periodo');

        $this->assertSame(1, BlockedPeriod::count());
        $this->assertNotNull($booking->fresh());
    }

    public function test_anonymizing_a_customer_keeps_their_bookings(): void
    {
        $booking = Booking::factory()->create();
        $customer = $booking->customer;

        Livewire::test(EditCustomer::class, ['record' => $customer->getRouteKey()])
            ->callAction('anonymize');

        $customer->refresh();
        $this->assertSame('Cliente anonimizado', $customer->name);
        $this->assertNull($customer->phone);
        $this->assertSame(1, $customer->bookings()->count());
        $this->assertSame(1, Customer::count());
    }

    public function test_agenda_shows_day_timeline_and_week(): void
    {
        $booking = Booking::factory()->create(['starts_at' => CarbonImmutable::parse('2026-11-04 10:00'), 'ends_at' => CarbonImmutable::parse('2026-11-04 11:00')]);

        Livewire::test(Agenda::class)
            ->assertDontSee('10:00–11:00') // today (Monday) is empty
            ->call('next')
            ->call('next')
            ->assertSee('10:00–11:00')
            ->assertSee(route('filament.admin.resources.bookings.create', ['space' => $booking->space_id, 'date' => '2026-11-04', 'start' => '12:00']))
            ->set('mode', 'semana')
            ->assertSee('10:00–11:00')
            ->call('next')
            ->assertDontSee('10:00–11:00');
    }
}
