<?php

use App\Http\Controllers\BookingPassController;
use App\Http\Controllers\BookingScanController;
use App\Http\Controllers\BroadcastAuthController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\MessageFlagController;
use App\Http\Controllers\MessageFlagResolveController;
use App\Http\Controllers\MessageFlagsIndexController;
use App\Http\Controllers\MessageSendController;
use App\Http\Controllers\MessageThreadController;
use App\Http\Controllers\MessageThreadArchiveController;
use App\Http\Controllers\MessageThreadMuteController;
use App\Http\Controllers\MessageThreadIndexController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\ThreadReadController;
use App\Http\Controllers\ThreadTypingController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthCheckController::class);
Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/bookings/{booking}/pass/{leg}', [BookingPassController::class, 'show'])
        ->whereIn('leg', ['A', 'B', 'a', 'b'])
        ->name('booking.pass.show');

    Route::post('/bookings/{booking}/scan/{leg}', BookingScanController::class)
        ->whereIn('leg', ['A', 'B', 'a', 'b'])
        ->name('booking.scan');

    Route::get('/messages/flags', MessageFlagsIndexController::class)->name('messages.flags.index');
    Route::post('/messages/flag/{message}', MessageFlagController::class)->name('messages.flag');
    Route::post('/messages/flag/{message}/resolve', MessageFlagResolveController::class)->name('messages.flag.resolve');

    Route::get('/messages', MessageThreadIndexController::class)->name('messages.index');
    Route::get('/messages/{thread}', [MessageThreadController::class, 'show'])->name('messages.show');
    Route::post('/messages/{thread}/send', MessageSendController::class)->name('messages.send');
    Route::post('/messages/{thread}/typing', ThreadTypingController::class)->name('messages.typing');
    Route::post('/messages/{thread}/read', ThreadReadController::class)->name('messages.read');
    Route::post('/messages/{thread}/archive', [MessageThreadArchiveController::class, 'store'])->name('messages.archive');
    Route::delete('/messages/{thread}/archive', [MessageThreadArchiveController::class, 'destroy'])->name('messages.archive.destroy');
    Route::post('/messages/{thread}/mute', [MessageThreadMuteController::class, 'store'])->name('messages.mute');
    Route::delete('/messages/{thread}/mute/{user}', [MessageThreadMuteController::class, 'destroy'])->name('messages.mute.destroy');

    Route::post('/broadcasting/auth', BroadcastAuthController::class)->name('broadcast.auth');
});