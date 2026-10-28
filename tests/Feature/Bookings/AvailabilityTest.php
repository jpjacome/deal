<?php

namespace Tests\Feature\Bookings;

use App\Enums\BookingStatus;
use App\Exceptions\BookingUnavailable;
use App\Models\BlockedPeriod;
use App\Models\Booking;
use App\Models\OpeningHour;
use App\Models\Space;
use App\Services\Availability;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Availability $availability;

    /** Wednesday, two days after the frozen "now". */
    private CarbonImmutable $wednesday;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-02 08:00')); // Monday
        $this->wednesday = CarbonImmutable::parse('2026-11-04');

        foreach ([1, 2, 3, 4, 5] as $weekday) {
            OpeningHour::create(['weekday' => $weekday, 'opens_at' => '08:00', 'closes_at' => '19:00']);
        }

        $this->availability = app(Availability::class);
    }

    private function at(string $time): CarbonImmutable
    {
        return $this->wednesday->setTimeFromTimeString($time);
    }

    public function test_start_times_follow_opening_hours_and_slot_grid(): void
    {
        $space = Space::factory()->create();

        $starts = $this->availability->availableStartTimes($space, $this->wednesday, 120);

        $this->assertSame('08:00', $starts[0]->format('H:i'));
        $this->assertSame('17:00', end($starts)->format('H:i'));
        $this->assertCount(10, $starts);
    }

    public function test_space_hours_replace_venue_hours(): void
    {
        $space = Space::factory()->create();
        OpeningHour::create(['space_id' => $space->id, 'weekday' => 3, 'opens_at' => '14:00', 'closes_at' => '16:00']);

        $starts = $this->availability->availableStartTimes($space, $this->wednesday, 60);

        $this->assertSame(['14:00', '15:00'], array_map(fn ($start) => $start->format('H:i'), $starts));
    }

    public function test_closed_days_have_no_start_times(): void
    {
        $space = Space::factory()->create();

        $this->assertSame([], $this->availability->availableStartTimes($space, CarbonImmutable::parse('2026-11-08'), 60)); // Sunday
    }

    public function test_existing_booking_blocks_overlapping_slots(): void
    {
        $space = Space::factory()->create();
        Booking::factory()->for($space)->create(['starts_at' => $this->at('10:00'), 'ends_at' => $this->at('12:00')]);

        $starts = array_map(fn ($start) => $start->format('H:i'), $this->availability->availableStartTimes($space, $this->wednesday, 60));

        $this->assertContains('09:00', $starts);
        $this->assertNotContains('10:00', $starts);
        $this->assertNotContains('11:00', $starts);
        $this->assertContains('12:00', $starts);
    }

    public function test_buffer_keeps_a_gap_around_bookings(): void
    {
        $space = Space::factory()->create(['buffer_minutes' => 15, 'slot_minutes' => 30, 'min_minutes' => 60]);
        Booking::factory()->for($space)->create(['starts_at' => $this->at('10:00'), 'ends_at' => $this->at('11:00')]);

        $starts = array_map(fn ($start) => $start->format('H:i'), $this->availability->availableStartTimes($space, $this->wednesday, 60));

        $this->assertNotContains('09:00', $starts); // would end 10:00, no gap before the booking
        $this->assertContains('08:30', $starts);    // ends 09:30, 30 min gap
        $this->assertNotContains('11:00', $starts); // starts right when the booking ends
        $this->assertContains('11:30', $starts);
    }

    public function test_cancelled_bookings_free_the_slot(): void
    {
        $space = Space::factory()->create();
        Booking::factory()->for($space)->status(BookingStatus::Cancelled)->create(['starts_at' => $this->at('10:00'), 'ends_at' => $this->at('12:00')]);

        $this->availability->ensureBookable($space, $this->at('10:00'), $this->at('12:00'));

        $this->assertTrue(true);
    }

    public function test_pending_bookings_hold_the_slot(): void
    {
        $space = Space::factory()->create();
        Booking::factory()->for($space)->status(BookingStatus::Pending)->create(['starts_at' => $this->at('10:00'), 'ends_at' => $this->at('12:00')]);

        $this->expectExceptionObject(new BookingUnavailable('Ese horario ya está reservado.'));

        $this->availability->ensureBookable($space, $this->at('11:00'), $this->at('13:00'));
    }

    public function test_blocked_periods_apply_to_their_space_and_venue_wide(): void
    {
        $space = Space::factory()->create();
        $other = Space::factory()->create();
        BlockedPeriod::factory()->create(['space_id' => $other->id, 'starts_at' => $this->at('08:00'), 'ends_at' => $this->at('19:00')]);
        BlockedPeriod::factory()->create(['space_id' => null, 'starts_at' => $this->at('15:00'), 'ends_at' => $this->at('19:00')]);

        $starts = array_map(fn ($start) => $start->format('H:i'), $this->availability->availableStartTimes($space, $this->wednesday, 60));

        $this->assertContains('14:00', $starts);
        $this->assertNotContains('15:00', $starts);
        $this->assertSame([], $this->availability->availableStartTimes($other, $this->wednesday, 60));
    }

    public function test_rejects_bookings_inside_the_lead_time(): void
    {
        $space = Space::factory()->create(['lead_time_minutes' => 120]);
        $today = CarbonImmutable::parse('2026-11-02');

        $this->expectException(BookingUnavailable::class);
        $this->expectExceptionMessage('2 horas de anticipación');

        $this->availability->ensureBookable($space, $today->setTime(9, 0), $today->setTime(10, 0));
    }

    public function test_staff_bookings_skip_lead_time_and_horizon(): void
    {
        $space = Space::factory()->create(['lead_time_minutes' => 120, 'horizon_days' => 7]);
        $today = CarbonImmutable::parse('2026-11-02');
        $farAway = CarbonImmutable::parse('2026-12-02'); // Wednesday

        $this->availability->ensureBookable($space, $today->setTime(9, 0), $today->setTime(10, 0), strict: false);
        $this->availability->ensureBookable($space, $farAway->setTime(9, 0), $farAway->setTime(10, 0), strict: false);

        $this->expectExceptionMessage('hasta 7 días por adelantado');
        $this->availability->ensureBookable($space, $farAway->setTime(9, 0), $farAway->setTime(10, 0));
    }

    public function test_rejects_durations_outside_limits_and_off_grid_starts(): void
    {
        $space = Space::factory()->create(['min_minutes' => 60, 'max_minutes' => 180]);

        foreach ([['10:00', '10:30'], ['10:00', '14:00'], ['10:30', '11:30'], ['18:00', '20:00']] as [$from, $to]) {
            try {
                $this->availability->ensureBookable($space, $this->at($from), $this->at($to));
                $this->fail("{$from}–{$to} should not be bookable.");
            } catch (BookingUnavailable) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_attendees_cannot_exceed_capacity(): void
    {
        $space = Space::factory()->create(['capacity' => 4]);

        $this->expectExceptionMessage('hasta 4 personas');

        $this->availability->ensureBookable($space, $this->at('10:00'), $this->at('11:00'), attendees: 5);
    }

    public function test_daily_spaces_are_booked_for_the_whole_day(): void
    {
        $space = Space::factory()->daily()->create();

        $this->assertSame(['08:00', '19:00'], array_map(fn ($time) => $time->format('H:i'), $this->availability->dayBounds($space, $this->wednesday)));
        $this->assertTrue($this->availability->isDayAvailable($space, $this->wednesday));

        Booking::factory()->for($space)->create(['starts_at' => $this->at('08:00'), 'ends_at' => $this->at('19:00')]);

        $this->assertFalse($this->availability->isDayAvailable($space, $this->wednesday));

        $this->expectExceptionMessage('por día completo');
        $this->availability->ensureBookable($space, $this->wednesday->addDay()->setTime(9, 0), $this->wednesday->addDay()->setTime(12, 0));
    }

    public function test_capacity_spaces_count_seats(): void
    {
        $space = Space::factory()->capacity(3)->create();
        Booking::factory()->for($space)->create(['starts_at' => $this->at('08:00'), 'ends_at' => $this->at('19:00'), 'attendees' => 2]);

        $this->assertTrue($this->availability->isDayAvailable($space, $this->wednesday, 1));
        $this->assertFalse($this->availability->isDayAvailable($space, $this->wednesday, 2));

        Booking::factory()->for($space)->create(['starts_at' => $this->at('08:00'), 'ends_at' => $this->at('19:00')]);

        $this->expectExceptionMessage('No quedan puestos disponibles');
        $this->availability->ensureBookable($space, $this->at('08:00'), $this->at('19:00'));
    }

    public function test_inactive_spaces_cannot_be_booked(): void
    {
        $space = Space::factory()->create(['is_bookable' => false]);

        $this->expectException(BookingUnavailable::class);

        $this->availability->ensureBookable($space, $this->at('10:00'), $this->at('11:00'));
    }
}
