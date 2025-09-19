<?php

namespace App\Domain\Bookings\Exceptions;

use App\Domain\Bookings\Enums\BookingEventType;
use App\Domain\Bookings\Enums\BookingStatus;
use RuntimeException;

class BookingStateTransitionException extends RuntimeException
{
    public static function illegalTransition(BookingStatus $from, BookingEventType $event): self
    {
        return new self("Event {$event->value} is not valid from state {$from->value}.");
    }

    public static function guardFailed(string $message): self
    {
        return new self($message);
    }

    public static function alreadyTerminal(BookingStatus $status): self
    {
        return new self("Booking already in terminal state {$status->value}.");
    }
}