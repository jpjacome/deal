<?php

namespace Tests\Feature\Admin;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Filament\Pages\Reports;
use App\Filament\Pages\Settings;
use App\Filament\Resources\BlockedPeriods\Pages\ManageBlockedPeriods;
use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Models\BlockedPeriod;
use App\Models\Booking;
use App\Models\OpeningHour;
use App\Models\Setting;
use App\Models\Space;
use App\Models\User;
use App\Notifications\BookingCancelled;
use App\Notifications\BookingRescheduled;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class AdminToolsTest extends TestCase
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

    private function wednesdayBooking(array $attributes = []): Booking
    {
        return Booking::factory()->create($attributes + [
            'starts_at' => CarbonImmutable::parse('2026-11-04 10:00'),
            'ends_at' => CarbonImmutable::parse('2026-11-04 12:00'),
        ]);
    }

    public function test_settings_are_saved_and_policy_changes_create_a_new_version(): void
    {
        Livewire::test(Settings::class)
            ->fillForm([
                'venue_address' => 'Calle Larga 1-23, Cuenca',
                'whatsapp_number' => '099 123 4567',
                'notify_emails' => ['equipo@deal.ec'],
                'privacy_policy' => 'Nueva política',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Calle Larga 1-23, Cuenca', Setting::get('venue_address'));
        $this->assertSame('+593991234567', Setting::get('whatsapp_number'));
        $this->assertSame(['equipo@deal.ec'], Setting::get('notify_emails'));
        $this->assertSame('2026-11-02 08:00', Setting::get('privacy_version'));
    }

    public function test_admin_phone_fields_store_full_international_numbers(): void
    {
        $customer = Booking::factory()->create()->customer;

        Livewire::test(EditCustomer::class, ['record' => $customer->getRouteKey()])
            ->fillForm(['phone' => '098 765 4321'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('+593987654321', $customer->fresh()->phone);

        Livewire::test(EditCustomer::class, ['record' => $customer->getRouteKey()])
            ->fillForm(['phone' => '123'])
            ->call('save')
            ->assertHasFormErrors(['phone']);

        Livewire::test(Settings::class)
            ->fillForm(['notify_whatsapp' => ['099 111 2233', '+1 305 555 0134']])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(['+593991112233', '+13055550134'], Setting::get('notify_whatsapp'));
    }

    public function test_only_admins_see_settings(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]));

        $this->get('/admin/settings')->assertForbidden();
    }

    public function test_staff_reschedules_and_the_customer_is_told(): void
    {
        Notification::fake();
        $booking = $this->wednesdayBooking();

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('reschedule', data: [
                'space_id' => $booking->space_id,
                'date' => '2026-11-05',
                'start_time' => '15:00',
                'end_time' => '17:00',
            ])
            ->assertNotified('Horario actualizado y cliente avisado');

        $this->assertSame('2026-11-05 15:00', $booking->fresh()->starts_at->format('Y-m-d H:i'));
        Notification::assertSentTo($booking->customer, BookingRescheduled::class);
    }

    public function test_check_in_is_available_on_the_day(): void
    {
        $booking = Booking::factory()->create([
            'starts_at' => CarbonImmutable::parse('2026-11-02 09:00'),
            'ends_at' => CarbonImmutable::parse('2026-11-02 11:00'),
        ]);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('checkIn');

        $this->assertNotNull($booking->fresh()->checked_in_at);
    }

    public function test_staff_creates_a_weekly_series_skipping_taken_weeks(): void
    {
        Notification::fake();
        $space = Space::factory()->create();
        Booking::factory()->for($space)->create([
            'starts_at' => CarbonImmutable::parse('2026-11-11 10:00'),
            'ends_at' => CarbonImmutable::parse('2026-11-11 11:00'),
        ]);

        Livewire::test(CreateBooking::class)
            ->fillForm([
                'space_id' => $space->id,
                'date' => '2026-11-04',
                'start_time' => '10:00',
                'end_time' => '11:00',
                'attendees' => 1,
                'name' => 'Equipo Andes',
                'repeat' => true,
                'repeat_until' => '2026-11-25',
            ])
            ->call('create')
            ->assertNotified('1 fecha(s) omitida(s)');

        $series = Booking::whereNotNull('series_id')->orderBy('starts_at')->get();
        $this->assertSame(['2026-11-04', '2026-11-18', '2026-11-25'], $series->map(fn ($b) => $b->starts_at->toDateString())->all());
        $this->assertCount(1, $series->pluck('series_id')->unique());
    }

    public function test_walk_in_without_email_and_half_day_booking(): void
    {
        $space = Space::factory()->daily()->create(['allows_half_day' => true]);

        Livewire::test(CreateBooking::class)
            ->fillForm([
                'space_id' => $space->id,
                'date' => '2026-11-04',
                'period' => 'pm',
                'attendees' => 1,
                'name' => 'Cliente sin correo',
                'phone' => '0991112233',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $booking = Booking::sole();
        $this->assertNull($booking->customer->email);
        $this->assertSame('13:00', $booking->starts_at->format('H:i'));
        $this->assertSame('19:00', $booking->ends_at->format('H:i'));
    }

    public function test_cancelling_bookings_hit_by_a_block_notifies_customers(): void
    {
        Notification::fake();
        $booking = $this->wednesdayBooking();
        $block = BlockedPeriod::factory()->create([
            'starts_at' => CarbonImmutable::parse('2026-11-04 08:00'),
            'ends_at' => CarbonImmutable::parse('2026-11-04 19:00'),
        ]);

        Livewire::test(ManageBlockedPeriods::class)
            ->callTableAction('cancelAffected', $block, data: ['reason' => 'Feriado local']);

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        Notification::assertSentTo($booking->customer, BookingCancelled::class);
    }

    public function test_reports_and_exports(): void
    {
        $this->wednesdayBooking(['status' => BookingStatus::Completed]);

        Livewire::test(Reports::class)
            ->assertSee('Ocupación por espacio')
            ->callAction('exportBookings', data: ['from' => '2026-11-01', 'until' => '2026-11-30'])
            ->assertFileDownloaded('reservas-2026-11-01-2026-11-30.csv');

        Livewire::test(Reports::class)
            ->callAction('exportCustomers')
            ->assertFileDownloaded('clientes-2026-11-02.csv');
    }

    public function test_calendar_feed_is_private(): void
    {
        $this->wednesdayBooking();
        $url = $this->admin->calendarFeedUrl();

        $this->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertSee('BEGIN:VEVENT', false);

        $this->get('/calendario/'.str_repeat('x', 48).'.ics')->assertNotFound();
    }

    public function test_reception_screen_needs_the_current_token(): void
    {
        $this->get('/pantalla/whatever')->assertNotFound();

        Setting::put(['screen_token' => 'secret-token']);
        $space = Space::factory()->create(['name' => 'Sala Andes']);
        Booking::factory()->for($space)->create([
            'starts_at' => CarbonImmutable::parse('2026-11-02 07:30'),
            'ends_at' => CarbonImmutable::parse('2026-11-02 09:00'),
        ]);

        $this->get('/pantalla/secret-token')
            ->assertOk()
            ->assertSee('Sala Andes')
            ->assertSee('Ocupado hasta las 09:00');
    }
}
