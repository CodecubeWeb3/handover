<?php

namespace App\Domain\Bookings\Enums;

enum BookingStatus: string
{
    case Scheduled = 'Scheduled';
    case AWindowOpen = 'A_WINDOW_OPEN';
    case AScanned = 'A_SCANNED';
    case Buffer = 'BUFFER';
    case BWindowOpen = 'B_WINDOW_OPEN';
    case BScanned = 'B_SCANNED';
    case Completed = 'COMPLETED';
    case NoShowA = 'NO_SHOW_A';
    case NoShowB = 'NO_SHOW_B';
    case Canceled = 'CANCELED';
    case Expired = 'EXPIRED';
    case Frozen = 'FROZEN';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::NoShowA,
            self::NoShowB,
            self::Canceled,
            self::Expired,
            self::Frozen,
        ], true);
    }
}