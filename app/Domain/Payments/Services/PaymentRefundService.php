<?php

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Enums\PaymentIntentStatus;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class PaymentRefundService
{
    public function __construct(
        private readonly SharedPaymentManager $payments,
        private readonly StripePaymentGateway $gateway
    ) {
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function refund(Booking $booking, array $legs, int $amountMinor, string $reason = 'manual_refund', array $context = []): Payment
    {
        $legs = array_map('strtoupper', $legs);

        return DB::transaction(function () use ($booking, $legs, $amountMinor, $reason) {
            $payment = $this->payments->ensurePreauthorized($booking)->fresh('intents');

            $remaining = $amountMinor > 0 ? $amountMinor : null;
            $refundedNow = 0;

            foreach ($payment->intents as $intent) {
                if (! in_array(strtoupper($intent->role), $legs, true)) {
                    continue;
                }

                $captured = $intent->amount_captured ?? 0;

                if ($captured <= 0) {
                    continue;
                }

                $amountForIntent = $remaining !== null ? min($remaining, $captured) : $captured;

                if ($amountForIntent <= 0) {
                    continue;
                }

                if ($intent->stripe_pi_id) {
                    $this->gateway->refund($intent->stripe_pi_id, $amountForIntent, $reason);
                }

                $refundedNow += $amountForIntent;

                $remaining = $remaining !== null ? max($remaining - $amountForIntent, 0) : null;
                $newCaptured = max($captured - $amountForIntent, 0);

                $intent->forceFill([
                    'status' => $newCaptured === 0 ? PaymentIntentStatus::Refunded : PaymentIntentStatus::Captured,
                    'amount_captured' => $newCaptured,
                ])->save();

                if ($remaining === 0) {
                    break;
                }
            }

            $refundTotal = $payment->refund_total + ($amountMinor > 0 ? $amountMinor : $refundedNow);

            $postCaptureTotal = $payment->intents()->sum('amount_captured');

            $payment->forceFill([
                'refund_total' => $refundTotal,
                'refund_reason' => $reason,
                'refunded_at' => now(),
                'status' => $postCaptureTotal > 0 ? PaymentStatus::Captured : PaymentStatus::Refunded,
            ])->save();

            return $payment->fresh('intents');
        });
    }

    public function markPayoutSettled(Payment $payment): Payment
    {
        $payment->forceFill([
            'payout_settled_at' => now(),
            'status' => $payment->status === PaymentStatus::Refunded
                ? PaymentStatus::Refunded
                : PaymentStatus::PayoutSettled,
        ])->save();

        if ($transfer = $payment->booking->transfer) {
            $transfer->forceFill([
                'settled_at' => now(),
                'status' => 'paid',
            ])->save();
        }

        return $payment->fresh('intents');
    }
}