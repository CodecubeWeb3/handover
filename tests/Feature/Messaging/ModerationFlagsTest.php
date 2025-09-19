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
        $service->flag($message, $booking->slot->request->parent, 'test');

        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        Sanctum::actingAs($admin, ['*']);

        $this->getJson(route('messages.flags.index'))
            ->assertOk()
            ->assertJsonStructure(['data' => [['message_id', 'reason', 'reporter', 'flagged_at']]]);

        $this->postJson(route('messages.flag.resolve', $message))
            ->assertOk()
            ->assertJson(['status' => 'resolved']);

        $this->assertDatabaseMissing('message_flags', ['message_id' => $message->id]);
    }

    public function test_non_admin_cannot_access_queue(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Parent->value]), ['*']);
        $this->getJson(route('messages.flags.index'))->assertForbidden();
    }
}