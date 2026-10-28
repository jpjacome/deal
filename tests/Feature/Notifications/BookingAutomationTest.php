<?php

namespace Tests\Feature\Notifications;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Setting;
use App\Notifications\BookingExpired;
use App\Notifications\BookingReminder;
use App\Notifications\MembershipNudge;
use App\Notifications\StaffBookingList;
use App\Notifications\VisitFeedback;
use App\Services\BookingAutomation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BookingAutomationTest extends TestCase
{
    use RefreshDatabase;

    private BookingAutomation $automation;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 08:00'));
        Setting::put(['notify_emails' => ['equipo@deal.test']]);
        $this->automation = app(BookingAutomation::class);
    }

    private function at(string $datetime): CarbonImmutable
    {
        return CarbonImmutable::parse($datetime);
    }

    public function test_pending_requests_expire_after_the_window(): void
    {
        $old = Booking::factory()->status(BookingStatus::Pending)->create(['created_at' => now()->subHours(25)]);
        $fresh = Booking::factory()->status(BookingStatus::Pending)->create(['created_at' => now()->subHour()]);

        $this->assertSame(1, $this->automation->expirePending());

        $this->assertSame(BookingStatus::Expired, $old->fresh()->status);
        $this->assertSame(BookingStatus::Pending, $fresh->fresh()->status);
        Notification::assertSentTo($old->customer, BookingExpired::class);
    }

    public function test_team_is_reminded_once_about_waiting_requests(): void
    {
        Booking::factory()->status(BookingStatus::Pending)->create(['created_at' => now()->subHours(5)]);

        $this->assertSame(1, $this->automation->remindStaffAboutPending());
        $this->assertSame(0, $this->automation->remindStaffAboutPending());

        Notification::assertSentOnDemandTimes(StaffBookingList::class, 1);
    }

    public function test_customers_get_one_reminder_before_their_visit(): void
    {
        $soon = Booking::factory()->create([
            'starts_at' => $this->at('2026-11-02 18:00'),
            'ends_at' => $this->at('2026-11-02 19:00'),
            'created_at' => now()->subDays(2),
        ]);
        $later = Booking::factory()->create([
            'starts_at' => $this->at('2026-11-05 10:00'),
            'ends_at' => $this->at('2026-11-05 11:00'),
            'created_at' => now()->subDays(2),
        ]);

        $this->assertSame(1, $this->automation->sendReminders());
        $this->assertSame(0, $this->automation->sendReminders());

        Notification::assertSentTo($soon->customer, BookingReminder::class);
        Notification::assertNotSentTo($later->customer, BookingReminder::class);
    }

    public function test_finished_bookings_are_completed(): void
    {
        $finished = Booking::factory()->create(['starts_at' => $this->at('2026-11-01 10:00'), 'ends_at' => $this->at('2026-11-01 12:00')]);
        $upcoming = Booking::factory()->create();

        $this->automation->closeFinished();

        $this->assertSame(BookingStatus::Completed, $finished->fresh()->status);
        $this->assertSame(BookingStatus::Confirmed, $upcoming->fresh()->status);
    }

    public function test_missed_check_in_becomes_no_show_when_enabled(): void
    {
        Setting::put(['checkin_grace_minutes' => 15]);
        $missed = Booking::factory()->create(['starts_at' => $this->at('2026-11-02 07:30'), 'ends_at' => $this->at('2026-11-02 09:30')]);
        $arrived = Booking::factory()->create(['starts_at' => $this->at('2026-11-02 07:30'), 'ends_at' => $this->at('2026-11-02 09:30'), 'checked_in_at' => now()]);

        $this->automation->closeFinished();

        $this->assertSame(BookingStatus::NoShow, $missed->fresh()->status);
        $this->assertSame(1, $missed->customer->fresh()->no_show_count);
        $this->assertSame(BookingStatus::Confirmed, $arrived->fresh()->status);
    }

    public function test_follow_ups_ask_for_a_review_and_suggest_a_plan_to_regulars(): void
    {
        Setting::put(['google_review_url' => 'https://g.page/r/deal/review', 'membership_nudge_after' => 2]);
        $customer = Customer::factory()->create();
        Booking::factory()->for($customer)->status(BookingStatus::Completed)->create([
            'starts_at' => $this->at('2026-10-20 10:00'), 'ends_at' => $this->at('2026-10-20 12:00'), 'feedback_sent_at' => now()->subWeek(),
        ]);
        $recent = Booking::factory()->for($customer)->status(BookingStatus::Completed)->create([
            'starts_at' => $this->at('2026-11-01 10:00'), 'ends_at' => $this->at('2026-11-01 12:00'),
        ]);

        $this->automation->sendFollowUps();
        $this->automation->sendFollowUps();

        Notification::assertSentToTimes($customer, VisitFeedback::class, 1);
        Notification::assertSentToTimes($customer, MembershipNudge::class, 1);
        $this->assertNotNull($recent->fresh()->feedback_sent_at);
    }

    public function test_daily_agenda_only_when_there_are_bookings(): void
    {
        $this->assertFalse($this->automation->sendDailyAgenda());

        Booking::factory()->create(['starts_at' => $this->at('2026-11-02 10:00'), 'ends_at' => $this->at('2026-11-02 11:00')]);

        $this->assertTrue($this->automation->sendDailyAgenda());
        Notification::assertSentOnDemand(StaffBookingList::class);
    }

    public function test_tick_command_runs(): void
    {
        $this->artisan('bookings:tick')->assertSuccessful();
        $this->artisan('bookings:daily-agenda')->assertSuccessful();
    }
}
