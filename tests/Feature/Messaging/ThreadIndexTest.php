<?php

namespace Tests\Feature\Messaging;

use App\Domain\Messaging\Services\MessageService;
use App\Models\Booking;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ThreadIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_can_list_threads(): void
    {
        $booking = Booking::factory()->create();
        $thread = app(MessageService::class)->ensureThread($booking);
        app(MessageService::class)->send($thread, $booking->slot->request->parent, 'Hello operative');

        Sanctum::actingAs($booking->operative, ['*']);

        $response = $this->getJson(route('messages.index'));

        $response->assertOk()->assertJsonStructure([
            'data' => [[
                'id',
                'booking_id',
                'participants',
                'last_message',
            ]],
        ]);
    }

    public function test_non_participant_cannot_list_threads(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['*']);

        $response = $this->getJson(route('messages.index'));
        $response->assertOk();
        $this->assertSame([], $response->json('data'));
    }
}