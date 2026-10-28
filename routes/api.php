<?php

use App\Http\Controllers\Api\SpaceController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:60,1')->group(function () {
    Route::get('spaces', [SpaceController::class, 'index']);
    Route::get('spaces/{space:slug}', [SpaceController::class, 'show']);
    Route::get('spaces/{space:slug}/availability', [SpaceController::class, 'availability']);
});

Route::get('webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle']);
