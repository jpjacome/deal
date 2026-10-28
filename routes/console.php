<?php

use App\Services\BookingAutomation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('bookings:tick', function (BookingAutomation $automation) {
    $this->info('Expiradas: '.$automation->expirePending());
    $this->info('Avisos de pendientes: '.$automation->remindStaffAboutPending());
    $this->info('Recordatorios: '.$automation->sendReminders());
    $this->info('Cerradas: '.$automation->closeFinished());
    $this->info('Seguimientos: '.$automation->sendFollowUps());
})->purpose('Expire, remind, close and follow up bookings');

Artisan::command('bookings:daily-agenda', function (BookingAutomation $automation) {
    $this->info($automation->sendDailyAgenda() ? 'Agenda enviada.' : 'Sin reservas hoy.');
})->purpose("Email today's agenda to the team");

// Production runs `php artisan schedule:run` every minute from cron (shared hosting has no daemon).
Schedule::command('bookings:tick')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('bookings:daily-agenda')->dailyAt('07:30');
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping();
