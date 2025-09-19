<?php

namespace App\Http\Controllers;

use App\Domain\Bookings\Services\PassTokenManager;
use App\Http\Requests\ShowPassPayloadRequest;
use App\Models\Booking;
use App\Models\HandoverToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class BookingPassController extends Controller
{
    public function show(ShowPassPayloadRequest $request, Booking $booking, string $leg, PassTokenManager $passTokenManager): JsonResponse
    {
        $leg = strtoupper($leg);

        if (! in_array($leg, [HandoverToken::LEG_A, HandoverToken::LEG_B], true)) {
            throw ValidationException::withMessages([
                'leg' => 'Unsupported booking leg.',
            ]);
        }

        if ($request->boolean('refresh')) {
            $token = $booking->handoverTokens()->firstWhere('leg', $leg);
            $result = $token
                ? $passTokenManager->rotate($token)
                : $passTokenManager->ensurePayloadFor($booking, $leg);
        } else {
            $result = $passTokenManager->ensurePayloadFor($booking, $leg);
        }

        return response()->json([
            'data' => $result['payload'],
        ]);
    }
}