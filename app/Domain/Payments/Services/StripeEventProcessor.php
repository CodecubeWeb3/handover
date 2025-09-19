<?php

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Enums\PaymentIntentStatus;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\Transfer;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Webhook;

class StripeEventProcessor
{
    public function __construct(private readonly ConfigRepository $config, private readonly PaymentRefundService $refundService)
    {
    }

    public function constructEvent(string $payload, string $signature): Event
    {
        $secret = $this->config->get('services.stripe.webhook.secret');

        return Webhook::constructEvent(
            $payload,
            $signature,
            $secret,
            (int) $this->config->get('services.stripe.webhook.tolerance', 300)
        );
    }

    public function handle(Event $event): void
    {
        $type = $event->type;
        $data = $event->data->object;

        match ($type) {
            'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($data),
            'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($data),
            'charge.refunded', 'charge.refund.updated' => $this->handleChargeRefunded($data),
            'transfer.paid' => $this->handleTransferPaid($data),
            default => null,
        };
    }

    private function handlePaymentIntentSucceeded(object $payload): void
    {
        $intent = PaymentIntent::query()->where('stripe_pi_id', $payload->id ?? null)->first();

        if (! $intent) {
            return;
        }

        $amountCaptured = (int) ($payload->amount_received ?? $payload->amount ?? 0);

        $intent->forceFill([
            'status' => PaymentIntentStatus::Captured,
            'amount_captured' => $amountCaptured,
            'last_status' => $payload->status ?? 'succeeded',
            'last_error' => null,
        ])->save();

        $payment = $intent->payment ?? Payment::query()->where('booking_id', $intent->booking_id)->first();

        if ($payment && $payment->status !== PaymentStatus::Captured) {
            $payment->forceFill([
                'status' => PaymentStatus::Captured,
            ])->save();
        }
    }

    private function handlePaymentIntentFailed(object $payload): void
    {
        $intent = PaymentIntent::query()->where('stripe_pi_id', $payload->id ?? null)->first();

        if (! $intent) {
            return;
        }

        $intent->forceFill([
            'status' => PaymentIntentStatus::Failed,
            'last_status' => $payload->status ?? 'failed',
            'last_error' => $payload->last_payment_error->message ?? null,
        ])->save();
    }

    private function handleChargeRefunded(object $payload): void
    {
        $paymentIntentId = $payload->payment_intent ?? null;

        if (! $paymentIntentId) {
            return;
        }

        $intent = PaymentIntent::query()->where('stripe_pi_id', $paymentIntentId)->first();

        if (! $intent) {
            return;
        }

        $payment = $intent->payment ?? Payment::query()->where('booking_id', $intent->booking_id)->first();

        if (! $payment) {
            return;
        }

        $amountRefunded = (int) ($payload->amount_refunded ?? $payload->amount ?? 0);

        $payment->forceFill([
            'refund_total' => max($payment->refund_total, $amountRefunded),
            'refund_reason' => $payload->reason ?? 'stripe_refund',
            'refunded_at' => now(),
            'status' => PaymentStatus::Refunded,
        ])->save();
    }

    private function handleTransferPaid(object $payload): void
    {
        $transferId = $payload->id ?? null;

        if (! $transferId) {
            return;
        }

        $transfer = Transfer::query()->where('stripe_transfer_id', $transferId)->first();

        if (! $transfer) {
            return;
        }

        $transfer->forceFill([
            'status' => 'paid',
            'settled_at' => now(),
        ])->save();

        if ($payment = Payment::query()->where('booking_id', $transfer->booking_id)->first()) {
            $this->refundService->markPayoutSettled($payment);
        }
    }
}