<?php

use App\Http\Controllers\BookingLookupController;
use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\ManageBookingController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ScreenController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/contacto', [PageController::class, 'contact'])->name('contact');
Route::get('/privacidad', [PageController::class, 'privacy'])->name('privacy');
Route::get('/condiciones', [PageController::class, 'terms'])->name('terms');

Route::view('/reservar', 'bookings.create')->name('bookings.create');

Route::middleware('throttle:30,1')->group(function () {
    Route::get('/reserva/{token}', [ManageBookingController::class, 'show'])->name('bookings.show');
    Route::get('/reserva/{token}/cambiar', [ManageBookingController::class, 'reschedule'])->name('bookings.reschedule');
    Route::get('/reserva/{token}/calendario.ics', [ManageBookingController::class, 'calendar'])->name('bookings.calendar');
    Route::post('/reserva/{token}/cancelar', [ManageBookingController::class, 'cancel'])->name('bookings.cancel');
});

Route::get('/mis-reservas', [BookingLookupController::class, 'show'])->name('bookings.lookup');
Route::post('/mis-reservas', [BookingLookupController::class, 'send'])->middleware('throttle:5,60')->name('bookings.lookup.send');

// Staff tools reached through private links.
Route::get('/calendario/{token}.ics', CalendarFeedController::class)->middleware('throttle:60,1')->name('calendar.feed');
Route::get('/pantalla/{token}', [ScreenController::class, 'show'])->name('screen');
Route::get('/espacios/{space:slug}/letrero', [ScreenController::class, 'doorSign'])->name('spaces.door-sign');
