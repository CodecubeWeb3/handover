<?php

namespace App\Http\Controllers;

use App\Domain\Messaging\Services\MessageService;
use App\Enums\UserRole;
use App\Models\MessageThread;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageThreadArchiveController extends Controller
{
    public function __construct(private readonly MessageService $service)
    {
    }

    public function store(Request $request, MessageThread $thread): JsonResponse
    {
        $user = $this->ensureModerator($request->user());

        $this->service->archiveThread($thread);

        $thread = $thread->refresh()->load('booking.slot.request.parent', 'booking.operative');

        return response()->json([
            'data' => $this->service->threadPayload($thread, $user),
        ]);
    }

    public function destroy(Request $request, MessageThread $thread): JsonResponse
    {
        $user = $this->ensureModerator($request->user());

        $this->service->unarchiveThread($thread);

        $thread = $thread->refresh()->load('booking.slot.request.parent', 'booking.operative');

        return response()->json([
            'data' => $this->service->threadPayload($thread, $user),
        ]);
    }

    private function ensureModerator(?User $user): User
    {
        abort_unless($user && in_array($user->role, [UserRole::Admin, UserRole::Moderator], true), 403);

        return $user;
    }
}
