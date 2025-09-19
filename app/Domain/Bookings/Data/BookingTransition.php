<?php

namespace App\Domain\Bookings\Data;

use App\Domain\Bookings\Enums\BookingEventType;
use App\Domain\Bookings\Enums\BookingStatus;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class BookingTransition
{
    public readonly string $eventUuid;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly BookingStatus $from,
        public readonly BookingStatus $to,
        public readonly BookingEventType $event,
        public readonly ?int $actorId = null,
        public readonly string $actorRole = 'system',
        public readonly array $metadata = [],
        ?string $eventUuid = null,
    ) {
        $this->eventUuid = $eventUuid ?? (string) Str::uuid();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function fromContext(BookingStatus $from, BookingStatus $to, BookingEventType $event, array $context = []): self
    {
        return new self(
            from: $from,
            to: $to,
            event: $event,
            actorId: Arr::get($context, 'actor_id'),
            actorRole: Arr::get($context, 'actor_role', 'system'),
            metadata: Arr::get($context, 'metadata', []),
            eventUuid: Arr::get($context, 'event_uuid'),
        );
    }
}