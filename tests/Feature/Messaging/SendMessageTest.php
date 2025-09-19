<?php

namespace Tests\Feature\Messaging;

use App\Domain\Messaging\Services\MessageService;
use App\Events\MessageSent;
use App\Models\Booking;
use App\Models\MessageThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SendMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_can_send_message(): void
    {
        $booking = Booking::factory()->create();
        $thread = app(MessageService::class)->ensureThread($booking);
        $parent = $booking->slot->request->parent;

        Sanctum::actingAs($parent, ['*']);
        Event::fake(MessageSent::class);

        $payload = [
            'message' => 'Hello from parent',
            'attachments' => [
                [
                    'storage_path' => 'messages/attachment.jpg',
                    'mime' => 'image/jpeg',
                    'bytes' => 1024,
                ],
            ],
        ];

        $response = $this->postJson(route('messages.send', ['thread' => $thread->id]), $payload);

        $response->assertCreated()->assertJsonPath('data.body', 'Hello from parent');

        $this->assertDatabaseHas('messages', [
            'thread_id' => $thread->id,
            'sender_id' => $parent->id,
            'body' => 'Hello from parent',
        ]);

        $this->assertDatabaseHas('message_attachments', [
            'storage_path' => 'messages/attachment.jpg',
            'bytes' => 1024,
        ]);

        Event::assertDispatched(MessageSent::class);
    }

    public function test_non_participant_cannot_send(): void
    {
        $booking = Booking::factory()->create();
        $thread = app(MessageService::class)->ensureThread($booking);

        Sanctum::actingAs(User::factory()->create(), ['*']);

        $response = $this->postJson(route('messages.send', ['thread' => $thread->id]), [
            'message' => 'blocked',
        ]);

        $response->assertForbidden();
    }
}