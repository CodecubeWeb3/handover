<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Message $message)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('booking.'.$this->message->thread?->booking_id)];
    }

    public function broadcastAs(): string
    {
        return 'thread.message';
    }

    public function broadcastWith(): array
    {
        $sender = $this->message->sender;

        return [
            'id' => $this->message->id,
            'thread_id' => $this->message->thread_id,
            'body' => $this->message->body,
            'sender' => [
                'id' => $sender?->id,
                'name' => $sender?->name,
                'role' => $sender?->role->value ?? null,
            ],
            'attachments' => $this->message->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'disk' => $attachment->storage_disk,
                'path' => $attachment->storage_path,
                'mime' => $attachment->mime,
                'bytes' => $attachment->bytes,
            ])->values()->all(),
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}