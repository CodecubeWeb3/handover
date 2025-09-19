<?php

namespace App\Events;

use App\Models\MessageThread;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageRead implements ShouldBroadcast
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public MessageThread $thread, public User $user, public ?int $messageId)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('booking.'.$this->thread->booking_id)];
    }

    public function broadcastAs(): string
    {
        return 'thread.read';
    }

    public function broadcastWith(): array
    {
        return [
            'thread_id' => $this->thread->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'role' => $this->user->role->value ?? null,
            ],
            'last_read_message_id' => $this->messageId,
            'emitted_at' => now()->toIso8601String(),
        ];
    }
}