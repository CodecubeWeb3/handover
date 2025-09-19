<?php

namespace Tests\Feature\Messaging;

use App\Domain\Messaging\Services\MessageService;
use App\Jobs\NotifyModeratorsOfFlag;
use App\Models\Booking;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FlagMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_can_flag_message(): void
    {
        $booking = Booking::factory()->create();
        $service = app(MessageService::class);
        $thread = $service->ensureThread($booking);
        $parent = $booking->slot->request->parent;

        $message = $service->send($thread, $parent, 'Content to flag');

        Sanctum::actingAs($booking->operative, ['*']);
        Queue::fake();

        $response = $this->postJson(route('messages.flag', ['message' => $message->id]), [
            'reason' => 'inappropriate',
        ]);

        $response->assertCreated()->assertJsonPath('data.reason', 'inappropriate');

        $this->assertDatabaseHas('message_flags', [
            'message_id' => $message->id,
            'reporter_id' => $booking->operative_id,
            'reason' => 'inappropriate',
        ]);

        Queue::assertPushed(NotifyModeratorsOfFlag::class);
    }

    public function test_non_participant_cannot_flag(): void
    {
        $booking = Booking::factory()->create();
        $service = app(MessageService::class);
        $thread = $service->ensureThread($booking);
        $message = $service->send($thread, $booking->slot->request->parent, 'Content');

        Sanctum::actingAs(User::factory()->create(), ['*']);

        $this->postJson(route('messages.flag', ['message' => $message->id]), [
            'reason' => 'spam',
        ])->assertForbidden();
    }
}