<?php

use App\Http\Controllers\ManageBookingController;
use App\Models\Space;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('home', [
    'spaces' => Space::bookable()->orderBy('sort')->orderBy('name')->get(),
]))->name('home');

Route::view('/reservar', 'bookings.create')->name('bookings.create');

Route::middleware('throttle:30,1')->group(function () {
    Route::get('/reserva/{token}', [ManageBookingController::class, 'show'])->name('bookings.show');
    Route::post('/reserva/{token}/cancelar', [ManageBookingController::class, 'cancel'])->name('bookings.cancel');
});
