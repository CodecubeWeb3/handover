<?php

namespace App\Domain\Bookings\Services;

use App\Domain\Bookings\Data\BookingTransition;
use App\Domain\Bookings\Enums\BookingEventType;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Exceptions\BookingStateTransitionException;
use App\Models\Booking;
use App\Models\BookingEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class BookingEventRecorder
{
    public function record(Booking $booking, BookingTransition $transition, array $context = []): BookingEvent
    {
        try {
            return DB::transaction(function () use ($booking, $transition, $context) {
                $latest = BookingEvent::query()
                    ->where('booking_id', $booking->id)
                    ->orderByDesc('chain_index')
                    ->lockForUpdate()
                    ->first();

                $chainIndex = $latest ? $latest->chain_index + 1 : 0;
                $prevHash = $latest?->this_hash;

                $payload = [
                    'event' => $transition->event->value,
                    'previous_status' => $transition->from->value,
                    'next_status' => $transition->to->value,
                    'actor' => [
                        'role' => $transition->actorRole,
                        'id' => $transition->actorId,
                    ],
                    'metadata' => $transition->metadata,
                    'context' => Arr::except($context, ['actor_id', 'actor_role', 'metadata', 'event_uuid']),
                ];

                $hashSource = ($prevHash ?? '') . json_encode([$transition->eventUuid, $payload], JSON_THROW_ON_ERROR);
                $thisHash = hex2bin(hash('sha256', $hashSource));

                return BookingEvent::create([
                    'booking_id' => $booking->id,
                    'event_uuid' => $transition->eventUuid,
                    'event_type' => $transition->event->value,
                    'payload_json' => $payload,
                    'chain_index' => $chainIndex,
                    'prev_hash' => $prevHash,
                    'this_hash' => $thisHash,
                    'created_at' => now(),
                ]);
            });
        } catch (QueryException $exception) {
            if ($this->isDuplicateEvent($exception)) {
                $existing = BookingEvent::query()->where('event_uuid', $transition->eventUuid)->first();

                if ($existing) {
                    return $existing;
                }
            }

            throw BookingStateTransitionException::guardFailed($exception->getMessage());
        }
    }

    private function isDuplicateEvent(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'uq_bkevt_uuid')
            || str_contains(strtolower($message), 'unique constraint failed: booking_events.event_uuid')
            || str_contains(strtolower($message), 'duplicate') && str_contains($message, 'event_uuid');
    }
}