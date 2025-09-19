<?php

namespace Tests\Unit\Jobs;

use App\Enums\UserRole;
use App\Jobs\NotifyModeratorsOfFlag;
use App\Models\MessageFlag;
use App\Models\User;
use App\Notifications\MessageFlaggedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotifyModeratorsOfFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_sent_to_admins(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $flag = MessageFlag::factory()->create();

        (new NotifyModeratorsOfFlag($flag))->handle();

        Notification::assertSentTo($admin, MessageFlaggedNotification::class);
    }
}