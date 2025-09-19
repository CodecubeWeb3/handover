<?php

namespace Tests\Feature\Messaging;

use App\Domain\Messaging\Services\MessageService;
use App\Events\MessageRead;
use App\Events\ThreadTyping;
use App\Models\Booking;
use App\Models\MessageReadState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ThreadSignalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_typing_event_dispatched(): void
    {
        $booking = Booking::factory()->create();
        $thread = app(MessageService::class)->ensureThread($booking);
        $parent = $booking->slot->request->parent;

        Sanctum::actingAs($parent, ['*']);
        Event::fake(ThreadTyping::class);

        $response = $this->postJson(route('messages.typing', ['thread' => $thread->id]), [
            'state' => 'started',
        ]);

        $response->assertOk();
        Event::assertDispatched(ThreadTyping::class);
    }

    public function test_read_event_dispatched_and_persisted(): void
    {
        $booking = Booking::factory()->create();
        $thread = app(MessageService::class)->ensureThread($booking);
        $operative = $booking->operative;

        Sanctum::actingAs($operative, ['*']);
        Event::fake(MessageRead::class);

        $response = $this->postJson(route('messages.read', ['thread' => $thread->id]), [
            'message_id' => 1,
        ]);

        $response->assertOk();
        Event::assertDispatched(MessageRead::class);

        $this->assertDatabaseHas('message_read_states', [
            'thread_id' => $thread->id,
            'user_id' => $operative->id,
        ]);
    }

    public function test_unauthorized_user_cannot_emit_signal(): void
    {
        $booking = Booking::factory()->create();
        $thread = app(MessageService::class)->ensureThread($booking);

        Sanctum::actingAs(User::factory()->create(), ['*']);

        $this->postJson(route('messages.typing', ['thread' => $thread->id]), [
            'state' => 'started',
        ])->assertForbidden();
    }
}