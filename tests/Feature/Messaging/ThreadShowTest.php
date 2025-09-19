<?php

namespace Tests\Feature\Messaging;

use App\Domain\Messaging\Services\MessageService;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ThreadShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_can_view_thread_payload(): void
    {
        $booking = Booking::factory()->create();
        $thread = app(MessageService::class)->ensureThread($booking);
        $service = app(MessageService::class);
        $service->send($thread, $booking->slot->request->parent, 'First message');
        $service->send($thread, $booking->operative, 'Reply message');

        Sanctum::actingAs($booking->slot->request->parent, ['*']);

        $response = $this->getJson(route('messages.show', $thread));

        $response->assertOk()->assertJsonStructure([
            'data' => [
                'thread' => ['id', 'booking_id', 'participants'],
                'messages',
                'read_states',
                'typing_states',
            ],
        ]);
    }

    public function test_non_participant_cannot_view_thread(): void
    {
        $booking = Booking::factory()->create();
        $thread = app(MessageService::class)->ensureThread($booking);

        Sanctum::actingAs(User::factory()->create(), ['*']);

        $this->getJson(route('messages.show', $thread))->assertForbidden();
    }
}