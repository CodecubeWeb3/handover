<?php

namespace App\Http\Controllers;

use App\Domain\Bookings\Enums\BookingEventType;
use App\Domain\Bookings\Exceptions\BookingStateTransitionException;
use App\Domain\Bookings\Services\BookingStateMachine;
use App\Http\Requests\ScanBookingRequest;
use App\Models\Booking;
use App\Models\BookingEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class BookingScanController extends Controller
{
    public function __invoke(ScanBookingRequest $request, Booking $booking, string $leg): JsonResponse
    {
        $leg = strtoupper($leg);

        $event = match ($leg) {
            'A' => BookingEventType::ScanAOk,
            'B' => BookingEventType::ScanBOk,
            default => throw ValidationException::withMessages([
                'leg' => 'Unsupported booking leg.',
            ]),
        };

        $validated = $request->validated();
        $eventUuid = $validated['event_uuid'];

        $existingEvent = $booking->events()->where('event_uuid', $eventUuid)->first();

        if ($existingEvent instanceof BookingEvent) {
            return response()->json([
                'data' => $this->formatEventResponse($booking, $existingEvent, true),
            ]);
        }

        $context = [
            'event_uuid' => $eventUuid,
            'actor_id' => $request->user()?->id,
            'actor_role' => $request->user()?->role->value ?? 'system',
            'location' => Arr::get($validated, 'location'),
            'device' => Arr::get($validated, 'device'),
            'token' => Arr::get($validated, 'token'),
        ];

        if (! empty($validated['metadata'])) {
            $context['metadata'] = $validated['metadata'];
        }

        try {
            $updatedBooking = app(BookingStateMachine::class)->apply($booking, $event, $context);
        } catch (BookingStateTransitionException $exception) {
            throw ValidationException::withMessages([
                'guards' => $exception->getMessage(),
            ]);
        }

        $eventRecord = $updatedBooking->events()->where('event_uuid', $eventUuid)->first();

        return response()->json([
            'data' => $this->formatEventResponse($updatedBooking, $eventRecord),
        ]);
    }

    private function formatEventResponse(Booking $booking, ?BookingEvent $event, bool $idempotent = false): array
    {
        $context = $event?->payload_json['context'] ?? [];

        return [
            'booking_id' => $booking->id,
            'status' => $booking->status,
            'event_uuid' => $event?->event_uuid,
            'event_type' => $event?->event_type,
            'guard_results' => $context['guard_results'] ?? null,
            'idempotent' => $idempotent,
            'recorded_at' => $event?->created_at?->toIso8601String(),
        ];
    }
}