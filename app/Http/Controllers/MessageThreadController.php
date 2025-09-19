<?php

namespace App\Http\Controllers;

use App\Domain\Messaging\Services\MessageService;
use App\Enums\UserRole;
use App\Models\MessageThread;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageThreadController extends Controller
{
    public function __construct(private readonly MessageService $service)
    {
    }

    public function show(Request $request, MessageThread $thread): JsonResponse
    {
        $user = $request->user();

        abort_unless($user && $this->canAccessThread($user, $thread), 403);

        return response()->json([
            'data' => $this->service->threadPayload(
                $thread->load('booking.slot.request.parent', 'booking.operative'),
                $user
            ),
        ]);
    }

    private function canAccessThread(User $user, MessageThread $thread): bool
    {
        if ($this->isModerator($user)) {
            return true;
        }

        $booking = $thread->booking;

        if (! $booking) {
            return false;
        }

        return in_array($user->id, [
            $booking->operative_id,
            $booking->slot?->request?->parent_id,
        ], true);
    }

    private function isModerator(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Moderator], true);
    }
}