<?php

namespace Tests\Feature\Messaging;

use App\Domain\Messaging\Services\MessageService;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ThreadShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_can_view_thread_payload(): void
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
                'thread' => [
                    'id',
                    'booking_id',
                    'participants',
                    'muted_until',
                    'participant_mutes',
                    'permissions' => ['can_archive', 'can_unarchive', 'can_mute', 'can_unmute'],
                ],
                'messages',
                'read_states',
                'typing_states',
            ],
        ]);
    }

    public function test_moderator_can_view_thread(): void
    {
        $booking = Booking::factory()->create();
        $thread = app(MessageService::class)->ensureThread($booking);
        $moderator = User::factory()->create(['role' => UserRole::Moderator->value]);

        Sanctum::actingAs($moderator, ['*']);

        $this->getJson(route('messages.show', $thread))->assertOk();
    }

    public function test_non_participant_cannot_view_thread(): void
    {
        $booking = Booking::factory()->create();
        $thread = app(MessageService::class)->ensureThread($booking);

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Parent->value]), ['*']);

        $this->getJson(route('messages.show', $thread))->assertForbidden();
    }
}
