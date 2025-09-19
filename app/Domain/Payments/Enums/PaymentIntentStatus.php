<?php

namespace App\Domain\Payments\Enums;

enum PaymentIntentStatus: string
{
    case RequiresCapture = 'requires_capture';
    case Captured = 'captured';
    case Canceled = 'canceled';
    case Refunded = 'refunded';
    case Failed = 'failed';
}