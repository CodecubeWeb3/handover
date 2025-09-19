<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Message;
use App\Models\MessageFlag;
use App\Models\MessageThread;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless(in_array($request->user()?->role, [UserRole::Admin, UserRole::Moderator], true), 403);

        $metrics = [
            'flagged' => MessageFlag::count(),
            'threads' => MessageThread::count(),
            'messages' => Message::count(),
            'bookings' => Booking::query()->whereHas('messageThread')->count(),
        ];

        return view('app.admin', compact('metrics'));
    }
}