<?php

namespace App\Http\Controllers;

use App\Domain\Messaging\Services\MessageService;
use App\Enums\UserRole;
use App\Http\Requests\ThreadMuteRequest;
use App\Models\MessageThread;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MessageThreadMuteController extends Controller
{
    public function __construct(private readonly MessageService $service)
    {
    }

    public function store(ThreadMuteRequest $request, MessageThread $thread): JsonResponse
    {
        $actor = $this->ensureModerator($request->user());

        $participant = User::query()->findOrFail($request->validated()['user_id']);

        if (! $this->participantInThread($thread, $participant->id)) {
            throw ValidationException::withMessages([
                'user_id' => 'Selected user is not part of this conversation.',
            ]);
        }

        $this->service->muteParticipant($thread, $participant, (int) $request->validated()['minutes']);

        $thread = $thread->refresh()->load('booking.slot.request.parent', 'booking.operative');

        return response()->json([
            'data' => $this->service->threadPayload($thread, $actor),
        ]);
    }

    public function destroy(Request $request, MessageThread $thread, User $participant): JsonResponse
    {
        $actor = $this->ensureModerator($request->user());

        if (! $this->participantInThread($thread, $participant->id)) {
            abort(404);
        }

        $this->service->unmuteParticipant($thread, $participant);

        $thread = $thread->refresh()->load('booking.slot.request.parent', 'booking.operative');

        return response()->json([
            'data' => $this->service->threadPayload($thread, $actor),
        ]);
    }

    private function ensureModerator(?User $user): User
    {
        abort_unless($user && in_array($user->role, [UserRole::Admin, UserRole::Moderator], true), 403);

        return $user;
    }

    private function participantInThread(MessageThread $thread, int $userId): bool
    {
        $thread->loadMissing('booking.slot.request');
        $booking = $thread->booking;

        if (! $booking) {
            return false;
        }

        return in_array($userId, [
            $booking->operative_id,
            $booking->slot?->request?->parent_id,
        ], true);
    }
}
