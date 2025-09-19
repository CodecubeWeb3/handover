<?php

namespace Tests\Feature\Messaging;

use App\Domain\Messaging\Services\MessageService;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ModerationFlagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_and_resolve_flags(): void
    {
        $booking = Booking::factory()->create();
        $service = app(MessageService::class);
        $thread = $service->ensureThread($booking);
        $message = $service->send($thread, $booking->operative, 'Needs review');
        $service->flag($message, $booking->slot->request->parent, 'test reason');

        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson(route('messages.flags.index', ['per_page' => 10]));

        $response->assertOk()->assertJsonStructure([
            'data' => [[
                'message_id',
                'reason',
                'reporter' => ['id', 'name'],
                'flagged_at',
                'thread_id',
                'booking_id',
                'preview',
            ]],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            'filters',
        ]);

        $this->postJson(route('messages.flag.resolve', $message))
            ->assertOk()
            ->assertJson(['status' => 'resolved']);

        $this->assertDatabaseMissing('message_flags', ['message_id' => $message->id]);
    }

    public function test_admin_can_filter_flags_by_booking_and_reporter(): void
    {
        $service = app(MessageService::class);

        $bookingA = Booking::factory()->create();
        $threadA = $service->ensureThread($bookingA);
        $messageA = $service->send($threadA, $bookingA->operative, 'Flag me A');
        $service->flag($messageA, $bookingA->slot->request->parent, 'reason A');

        $bookingB = Booking::factory()->create();
        $threadB = $service->ensureThread($bookingB);
        $messageB = $service->send($threadB, $bookingB->operative, 'Flag me B');
        $service->flag($messageB, $bookingB->operative, 'reason B');

        $admin = User::factory()->create(['role' => UserRole::Moderator->value]);
        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson(route('messages.flags.index', [
            'booking_id' => $bookingA->id,
            'reporter' => $bookingA->slot->request->parent->name,
        ]));

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame($messageA->id, $response->json('data.0.message_id'));
    }

    public function test_non_admin_cannot_access_queue(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Parent->value]), ['*']);
        $this->getJson(route('messages.flags.index'))->assertForbidden();
    }
}

