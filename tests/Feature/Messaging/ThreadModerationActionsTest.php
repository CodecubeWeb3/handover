<?php

namespace Tests\Feature\Messaging;

use App\Domain\Messaging\Services\MessageService;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ThreadModerationActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_moderator_can_archive_and_unarchive_thread(): void
    {
        $booking = Booking::factory()->create();
        $thread = app(MessageService::class)->ensureThread($booking);
        $moderator = User::factory()->create(['role' => UserRole::Moderator->value]);

        Sanctum::actingAs($moderator, ['*']);

        $this->postJson(route('messages.archive', $thread))
            ->assertOk()
            ->assertJsonPath('data.thread.archived_at', fn ($value) => ! is_null($value));

        $thread->refresh();
        $this->assertNotNull($thread->archived_at);

        $this->deleteJson(route('messages.archive.destroy', $thread))
            ->assertOk()
            ->assertJsonPath('data.thread.archived_at', null);

        $thread->refresh();
        $this->assertNull($thread->archived_at);
    }

    public function test_non_moderator_cannot_archive_thread(): void
    {
        $booking = Booking::factory()->create();
        $thread = app(MessageService::class)->ensureThread($booking);
        Sanctum::actingAs($booking->operative, ['*']);

        $this->postJson(route('messages.archive', $thread))->assertForbidden();
    }

    public function test_moderator_can_mute_and_unmute_participant(): void
    {
        $booking = Booking::factory()->create();
        $thread = app(MessageService::class)->ensureThread($booking);
        $moderator = User::factory()->create(['role' => UserRole::Admin->value]);
        $participant = $booking->slot->request->parent;

        Sanctum::actingAs($moderator, ['*']);

        $this->postJson(route('messages.mute', $thread), [
            'user_id' => $participant->id,
            'minutes' => 45,
        ])->assertOk()->assertJsonPath('data.thread.participant_mutes.0.user.id', $participant->id);

        $this->assertDatabaseHas('message_mutes', [
            'thread_id' => $thread->id,
            'user_id' => $participant->id,
        ]);

        $this->deleteJson(route('messages.mute.destroy', [$thread, $participant]))
            ->assertOk()
            ->assertJsonPath('data.thread.participant_mutes', []);

        $this->assertDatabaseMissing('message_mutes', [
            'thread_id' => $thread->id,
            'user_id' => $participant->id,
        ]);
    }

    public function test_non_moderator_cannot_mute(): void
    {
        $booking = Booking::factory()->create();
        $thread = app(MessageService::class)->ensureThread($booking);
        $participant = $booking->slot->request->parent;

        Sanctum::actingAs($participant, ['*']);

        $this->postJson(route('messages.mute', $thread), [
            'user_id' => $participant->id,
            'minutes' => 30,
        ])->assertForbidden();
    }
}
