<?php

namespace App\Domain\Payments\Enums;

enum PaymentStatus: string
{
    case Preauthorized = 'preauthorized';
    case Captured = 'captured';
    case Refunded = 'refunded';
    case Canceled = 'canceled';
    case PayoutPending = 'payout_pending';
    case PayoutSettled = 'payout_settled';
    case Failed = 'failed';
}