<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BroadcastAuthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $channel = (string) $request->input('channel_name');

        if (! $user || $channel === '') {
            abort(403);
        }

        if (! $this->authorizedForChannel($user->id, $channel)) {
            abort(403);
        }

        return response()->json([
            'authorized' => true,
            'user_id' => $user->id,
        ]);
    }

    private function authorizedForChannel(int $userId, string $channel): bool
    {
        if (! preg_match('/(?:private|presence)-booking\.(\d+)/', $channel, $matches)) {
            return false;
        }

        $bookingId = (int) $matches[1];
        $booking = Booking::query()->find($bookingId);

        if (! $booking) {
            return false;
        }

        $parentId = (int) $booking->slot?->request?->parent_id;
        $operativeId = (int) $booking->operative_id;

        return in_array($userId, [$parentId, $operativeId], true);
    }
}