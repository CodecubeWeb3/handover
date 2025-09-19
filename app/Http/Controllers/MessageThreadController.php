<?php

namespace App\Http\Controllers;

use App\Domain\Messaging\Services\MessageService;
use App\Models\MessageThread;
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

        abort_unless($user && $this->participatesInThread($user->id, $thread), 403);

        return response()->json([
            'data' => $this->service->threadPayload(
                $thread->load('booking.slot.request.parent', 'booking.operative'),
                $user
            ),
        ]);
    }

    private function participatesInThread(int $userId, MessageThread $thread): bool
    {
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