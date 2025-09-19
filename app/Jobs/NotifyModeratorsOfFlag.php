<?php

namespace App\Jobs;

use App\Enums\UserRole;
use App\Models\MessageFlag;
use App\Models\User;
use App\Notifications\MessageFlaggedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class NotifyModeratorsOfFlag implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 3;

    public function __construct(public MessageFlag $flag)
    {
    }

    public function handle(): void
    {
        $recipients = User::query()
            ->whereIn('role', [UserRole::Admin->value, UserRole::Moderator->value])
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new MessageFlaggedNotification($this->flag));
    }
}