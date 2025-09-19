<?php

namespace App\Domain\Messaging\Services;

use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\ThreadTyping;
use App\Jobs\NotifyModeratorsOfFlag;
use App\Models\Booking;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageFlag;
use App\Models\MessageReadState;
use App\Models\MessageThread;
use App\Models\MessageTypingState;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MessageService
{
    public function ensureThread(Booking $booking): MessageThread
    {
        return MessageThread::query()->firstOrCreate([
            'booking_id' => $booking->id,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listThreadsForUser(User $user): array
    {
        $threads = MessageThread::query()
            ->with([
                'booking.slot.request.parent',
                'booking.operative',
                'messages' => fn ($query) => $query->latest('created_at')->limit(1)->with('sender'),
                'readStates',
            ])
            ->whereHas('booking', function ($query) use ($user) {
                $query->where('operative_id', $user->id)
                    ->orWhereHas('slot.request', fn ($q) => $q->where('parent_id', $user->id));
            })
            ->orderByDesc('updated_at')
            ->get();

        return $threads->map(function (MessageThread $thread) use ($user) {
            $booking = $thread->booking;
            $lastMessage = $thread->messages->first();
            $userReadState = $thread->readStates->firstWhere('user_id', $user->id);
            $lastReadId = $userReadState?->message_id ?? 0;
            $unread = $lastMessage && ($lastReadId < $lastMessage->id);

            return [
                'id' => $thread->id,
                'booking_id' => $booking?->id,
                'last_message' => $lastMessage ? [
                    'id' => $lastMessage->id,
                    'body' => $lastMessage->body,
                    'created_at' => optional($lastMessage->created_at)->toIso8601String(),
                    'sender' => [
                        'id' => $lastMessage->sender?->id,
                        'name' => $lastMessage->sender?->name,
                        'role' => $lastMessage->sender?->role->value ?? null,
                    ],
                ] : null,
                'participants' => $this->participantsPayload($thread),
                'unread' => $unread,
            ];
        })->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function threadPayload(MessageThread $thread, User $user): array
    {
        $messages = $thread->messages()
            ->with(['sender', 'attachments'])
            ->orderBy('created_at')
            ->take(100)
            ->get();

        $readStates = $thread->readStates()->with('user')->get();
        $typingStates = $this->activeTypingStates($thread);

        return [
            'thread' => [
                'id' => $thread->id,
                'booking_id' => $thread->booking_id,
                'participants' => $this->participantsPayload($thread),
            ],
            'messages' => $messages->map(fn (Message $message) => [
                'id' => $message->id,
                'body' => $message->body,
                'created_at' => optional($message->created_at)->toIso8601String(),
                'sender' => [
                    'id' => $message->sender?->id,
                    'name' => $message->sender?->name,
                    'role' => $message->sender?->role->value ?? null,
                ],
                'attachments' => $message->attachments->map(fn ($attachment) => [
                    'id' => $attachment->id,
                    'disk' => $attachment->storage_disk,
                    'path' => $attachment->storage_path,
                    'mime' => $attachment->mime,
                    'bytes' => $attachment->bytes,
                ])->all(),
            ])->all(),
            'read_states' => $readStates->map(fn (MessageReadState $state) => [
                'user' => [
                    'id' => $state->user?->id,
                    'name' => $state->user?->name,
                    'role' => $state->user?->role->value ?? null,
                ],
                'message_id' => $state->message_id,
                'read_at' => optional($state->read_at)->toIso8601String(),
            ])->all(),
            'typing_states' => $typingStates->map(fn (MessageTypingState $state) => [
                'user' => [
                    'id' => $state->user?->id,
                    'name' => $state->user?->name,
                    'role' => $state->user?->role->value ?? null,
                ],
                'state' => $state->state,
                'updated_at' => optional($state->updated_at)->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * @param  array<int, array{storage_disk?: string, storage_path: string, mime: string, bytes: int}>  $attachments
     */
    public function send(MessageThread $thread, User $sender, string $body, array $attachments = []): Message
    {
        if ($body === '' && empty($attachments)) {
            throw new \InvalidArgumentException('Message body or attachment required.');
        }

        return DB::transaction(function () use ($thread, $sender, $body, $attachments) {
            $message = Message::query()->create([
                'thread_id' => $thread->id,
                'sender_id' => $sender->id,
                'body' => $body,
                'created_at' => now(),
            ]);

            foreach ($attachments as $attachment) {
                MessageAttachment::query()->create([
                    'message_id' => $message->id,
                    'storage_disk' => Arr::get($attachment, 'storage_disk', config('filesystems.default')),
                    'storage_path' => Arr::get($attachment, 'storage_path'),
                    'mime' => Arr::get($attachment, 'mime'),
                    'bytes' => (int) Arr::get($attachment, 'bytes', 0),
                    'created_at' => now(),
                ]);
            }

            MessageReadState::query()->updateOrCreate(
                ['thread_id' => $thread->id, 'user_id' => $sender->id],
                ['message_id' => $message->id, 'read_at' => now()]
            );

            $message->load(['sender', 'attachments', 'thread.booking']);

            event(new MessageSent($message));

            return $message;
        });
    }

    public function emitTyping(MessageThread $thread, User $user, string $state): void
    {
        MessageTypingState::query()->updateOrCreate(
            ['thread_id' => $thread->id, 'user_id' => $user->id],
            ['state' => $state, 'updated_at' => now()]
        );

        event(new ThreadTyping($thread, $user, $state));
    }

    public function emitRead(MessageThread $thread, User $user, ?int $messageId = null): void
    {
        if ($messageId !== null && ! Message::query()->where('thread_id', $thread->id)->where('id', $messageId)->exists()) {
            $messageId = null;
        }

        MessageReadState::query()->updateOrCreate(
            ['thread_id' => $thread->id, 'user_id' => $user->id],
            ['message_id' => $messageId, 'read_at' => now()]
        );

        event(new MessageRead($thread, $user, $messageId));
    }

    public function flag(Message $message, User $reporter, string $reason): MessageFlag
    {
        $flag = MessageFlag::query()->updateOrCreate([
            'message_id' => $message->id,
            'reporter_id' => $reporter->id,
        ], [
            'reason' => $reason,
            'created_at' => now(),
        ]);

        NotifyModeratorsOfFlag::dispatch($flag);

        return $flag;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listFlags(): array
    {
        return MessageFlag::query()
            ->with(['message.thread.booking', 'reporter'])
            ->latest('created_at')
            ->get()
            ->map(fn (MessageFlag $flag) => [
                'message_id' => $flag->message_id,
                'reason' => $flag->reason,
                'flagged_at' => optional($flag->created_at)->toIso8601String(),
                'reporter' => [
                    'id' => $flag->reporter?->id,
                    'name' => $flag->reporter?->name,
                    'role' => $flag->reporter?->role->value ?? null,
                ],
                'thread_id' => $flag->message?->thread_id,
                'preview' => $flag->message?->body,
            ])->all();
    }

    public function resolveFlags(Message $message): void
    {
        MessageFlag::query()->where('message_id', $message->id)->delete();
    }

    /**
     * @return Collection<int, MessageTypingState>
     */
    private function activeTypingStates(MessageThread $thread): Collection
    {
        $cutoff = now()->subSeconds(15);

        return MessageTypingState::query()
            ->where('thread_id', $thread->id)
            ->where('state', 'started')
            ->where('updated_at', '>=', $cutoff)
            ->with('user')
            ->get();
    }

    /**
     * @return array<int, array{id: int|null, name: string|null, role: string|null}>
     */
    private function participantsPayload(MessageThread $thread): array
    {
        $booking = $thread->booking;
        $participants = collect();

        if ($parent = $booking?->slot?->request?->parent) {
            $participants->push([
                'id' => $parent->id,
                'name' => $parent->name,
                'role' => $parent->role->value ?? null,
            ]);
        }

        if ($operative = $booking?->operative) {
            $participants->push([
                'id' => $operative->id,
                'name' => $operative->name,
                'role' => $operative->role->value ?? null,
            ]);
        }

        return $participants->unique('id')->values()->all();
    }
}