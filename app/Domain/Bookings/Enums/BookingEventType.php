<?php

namespace App\Domain\Bookings\Enums;

enum BookingEventType: string
{
    case OpenAWindow = 'open_A_window';
    case ScanAOk = 'scan_A_ok';
    case TimerAGraceExpired = 'timer_A_grace_expired';
    case BufferElapsed = 'buffer_elapsed';
    case OpenBWindow = 'open_B_window';
    case ScanBOk = 'scan_B_ok';
    case TimerBGraceExpired = 'timer_B_grace_expired';
    case CancelByParent = 'cancel_by_parent';
    case CancelByOperative = 'cancel_by_operative';
    case CancelByAdmin = 'cancel_by_admin';
    case TimeoutAll = 'timeout_all';
    case GeoFreeze = 'geo_freeze';
    case Unfreeze = 'unfreeze';
    case Complete = 'complete';
}