<?php

use App\Enums\UserRole;
use App\Http\Controllers\AdminDashboardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('/dashboard', 'app.dashboard')->name('dashboard');

    Route::get('/messages', function () {
        return view('app.messages');
    })->name('messages.page');

    Route::get('/moderation/flags', function () {
        abort_unless(in_array(auth()->user()?->role, [UserRole::Admin, UserRole::Moderator], true), 403);
        return view('app.moderation');
    })->name('moderation.flags');

    Route::get('/admin', AdminDashboardController::class)->name('admin.dashboard');
});