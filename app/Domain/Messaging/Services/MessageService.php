<?php

namespace App\Domain\Messaging\Services;

use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\ThreadTyping;

use App\Enums\UserRole;
use App\Jobs\NotifyModeratorsOfFlag;
use App\Models\Booking;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageFlag;
use App\Models\MessageMute;
use App\Models\MessageReadState;
use App\Models\MessageThread;
use App\Models\MessageTypingState;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
    public function listThreadsForUser(User $user, bool $includeArchived = false): array
    {
        $threads = MessageThread::query()
            ->with([
                'booking.slot.request.parent',
                'booking.operative',
                'messages' => fn ($query) => $query->latest('created_at')->limit(1)->with('sender'),
                'readStates',
                'mutes',
            ])
            ->when(! $includeArchived, fn ($query) => $query->whereNull('archived_at'))
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
            $mute = $thread->mutes->firstWhere('user_id', $user->id);

            return [
                'id' => $thread->id,
                'booking_id' => $booking?->id,
                'archived_at' => optional($thread->archived_at)->toIso8601String(),
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
                'muted_until' => optional($mute?->muted_until)->toIso8601String(),
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
        $mute = $this->muteForUser($thread, $user);
        $mutes = $thread->mutes()->with('user')->get();

        return [
            'thread' => [
                'id' => $thread->id,
                'booking_id' => $thread->booking_id,
                'archived_at' => optional($thread->archived_at)->toIso8601String(),
                'participants' => $this->participantsPayload($thread),
                'muted_until' => optional($mute?->muted_until)->toIso8601String(),
                'participant_mutes' => $mutes->map(fn (MessageMute $record) => [
                    'user' => [
                        'id' => $record->user?->id,
                        'name' => $record->user?->name,
                        'role' => $record->user?->role->value ?? null,
                    ],
                    'muted_until' => optional($record->muted_until)->toIso8601String(),
                    'created_at' => optional($record->created_at)->toIso8601String(),
                ])->values()->all(),
                'permissions' => $this->threadPermissions($thread, $user),
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

    public function send(MessageThread $thread, User $sender, string $body, array $attachments = []): Message
    {
        if ($body === '' && empty($attachments)) {
            throw ValidationException::withMessages([
                'message' => 'Message body or attachment required.',
            ]);
        }

        if ($this->isMuted($thread, $sender)) {
            $mute = $this->muteForUser($thread, $sender);
            $until = optional($mute?->muted_until)->toDayDateTimeString() ?? 'further notice';

            throw ValidationException::withMessages([
                'message' => "You are muted in this conversation until {$until}.",
            ]);
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

            $thread->forceFill(['updated_at' => now()])->save();

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

    public function listFlags(array $filters, int $perPage = 15, int $page = 1): array
    {
        $perPage = max(1, min($perPage, 100));
        $page = max($page, 1);

        $paginator = $this->flagQuery($filters)->paginate($perPage, ['*'], 'page', $page);

        $data = collect($paginator->items())->map(function (MessageFlag $flag) {
            $message = $flag->message;
            $thread = $message?->thread;

            return [
                'message_id' => $flag->message_id,
                'reason' => $flag->reason,
                'flagged_at' => optional($flag->created_at)->toIso8601String(),
                'reporter' => [
                    'id' => $flag->reporter?->id,
                    'name' => $flag->reporter?->name,
                ],
                'thread_id' => $thread?->id,
                'booking_id' => $thread?->booking_id,
                'preview' => $message?->body,
            ];
        })->all();

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'filters' => array_filter([
                'reason' => $filters['reason'] ?? null,
                'reporter' => $filters['reporter'] ?? null,
                'booking_id' => $filters['booking_id'] ?? null,
                'thread_id' => $filters['thread_id'] ?? null,
                'message_id' => $filters['message_id'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''),
        ];
    }

    private function flagQuery(array $filters): Builder
    {
        $query = MessageFlag::query()
            ->with(['message.thread.booking', 'reporter'])
            ->latest('created_at');

        if (! empty($filters['reason'])) {
            $query->where('reason', 'like', '%'.$filters['reason'].'%');
        }

        if (! empty($filters['reporter'])) {
            $query->whereHas('reporter', fn ($q) => $q->where('name', 'like', '%'.$filters['reporter'].'%'));
        }

        if (! empty($filters['booking_id'])) {
            $query->whereHas('message.thread', fn ($q) => $q->where('booking_id', $filters['booking_id']));
        }

        if (! empty($filters['thread_id'])) {
            $query->whereHas('message', fn ($q) => $q->where('thread_id', $filters['thread_id']));
        }

        if (! empty($filters['message_id'])) {
            $query->where('message_id', $filters['message_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    public function resolveFlags(Message $message): void
    {
        MessageFlag::query()->where('message_id', $message->id)->delete();
    }

    public function archiveThread(MessageThread $thread): void
    {
        $thread->forceFill(['archived_at' => now()])->save();
    }

    public function unarchiveThread(MessageThread $thread): void
    {
        $thread->forceFill(['archived_at' => null])->save();
    }

    public function muteParticipant(MessageThread $thread, User $user, int $minutes): MessageMute
    {
        $mutedUntil = now()->addMinutes(max($minutes, 1));

        return MessageMute::query()->updateOrCreate(
            ['thread_id' => $thread->id, 'user_id' => $user->id],
            ['muted_until' => $mutedUntil, 'created_at' => now()]
        );
    }

    public function unmuteParticipant(MessageThread $thread, User $user): void
    {
        MessageMute::query()->where('thread_id', $thread->id)->where('user_id', $user->id)->delete();
    }

    private function threadPermissions(MessageThread $thread, User $user): array
    {
        $canModerate = $this->canModerate($user);

        return [
            'can_archive' => $canModerate,
            'can_unarchive' => $canModerate && $thread->archived_at !== null,
            'can_mute' => $canModerate,
            'can_unmute' => $canModerate,
        ];
    }

    private function canModerate(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return in_array($user->role, [UserRole::Admin, UserRole::Moderator], true);
    }

    private function isMuted(MessageThread $thread, User $user): bool
    {
        $mute = $this->muteForUser($thread, $user);

        if (! $mute) {
            return false;
        }

        if ($mute->muted_until && $mute->muted_until->isPast()) {
            $this->unmuteParticipant($thread, $user);

            return false;
        }

        return true;
    }

    private function muteForUser(MessageThread $thread, User $user): ?MessageMute
    {
        if ($thread->relationLoaded('mutes')) {
            return $thread->mutes->firstWhere('user_id', $user->id);
        }

        return $thread->mutes()
            ->where('user_id', $user->id)
            ->latest('muted_until')
            ->first();
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
