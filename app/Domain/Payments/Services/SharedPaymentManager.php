<?php

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Data\ChargeBreakdown;
use App\Domain\Payments\Enums\PaymentIntentStatus;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\Transfer;
use App\Support\FeatureFlags;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SharedPaymentManager
{
    public function __construct(
        private readonly ChargeCalculator $calculator,
        private readonly FeatureFlags $flags,
        private readonly StripePaymentGateway $gateway
    ) {
    }

    public function ensurePreauthorized(Booking $booking): Payment
    {
        return DB::transaction(function () use ($booking) {
            $breakdown = $this->calculator->forBooking($booking);
            $payment = Payment::query()->firstOrNew(['booking_id' => $booking->id]);

            $payment->forceFill([
                'currency' => $breakdown->currency,
                'amount_total' => $breakdown->bookingAmount,
                'platform_fee' => $breakdown->platformFee,
                'late_fee_a' => $breakdown->lateFeeA,
                'late_fee_b' => $breakdown->lateFeeB,
                'travel_stipend_a' => $breakdown->stipendA,
                'travel_stipend_b' => $breakdown->stipendB,
                'status' => PaymentStatus::Preauthorized,
            ])->save();

            $this->syncIntents($booking, $payment, $breakdown);

            return $payment->fresh('intents');
        });
    }

    public function captureForCompletion(Booking $booking): Payment
    {
        return DB::transaction(function () use ($booking) {
            $payment = $this->ensurePreauthorized($booking)->fresh('intents');

            if ($payment->status === PaymentStatus::Captured) {
                return $payment;
            }

            $baseBreakdown = $this->calculator->forBooking($booking);
            $breakdown = $this->calculator->applyLateFees($booking, $baseBreakdown);
            $captureMap = $breakdown->legTotals();

            $this->captureIntents($booking, $payment, $breakdown, $captureMap);

            $capturedTotal = $payment->intents->sum('amount_captured');
            $platformShare = $this->calculatePlatformShare($capturedTotal, $breakdown);

            $payment->forceFill([
                'amount_total' => $capturedTotal,
                'platform_fee' => $platformShare,
                'late_fee_a' => $breakdown->lateFeeA,
                'late_fee_b' => $breakdown->lateFeeB,
                'travel_stipend_a' => $breakdown->stipendA,
                'travel_stipend_b' => $breakdown->stipendB,
                'status' => PaymentStatus::Captured,
            ])->save();

            $this->upsertTransfer($booking, $capturedTotal, $platformShare);

            return $payment->fresh('intents');
        });
    }

    public function captureForNoShowA(Booking $booking): Payment
    {
        return DB::transaction(function () use ($booking) {
            $payment = $this->ensurePreauthorized($booking)->fresh('intents');

            $baseBreakdown = $this->calculator->forBooking($booking);
            $breakdown = $this->calculator->applyLateFees($booking, $baseBreakdown);

            $minThreshold = $this->minimumCaptureThreshold($booking, $breakdown);
            $legACharge = max($minThreshold, $breakdown->lateFeeA + $breakdown->stipendA);

            $captureMap = [
                'A' => $legACharge,
                'B' => 0,
            ];

            $this->captureIntents($booking, $payment, $breakdown, $captureMap, cancelLegB: true);

            $capturedTotal = $legACharge;
            $platformShare = $this->calculatePlatformShare($capturedTotal, $breakdown);

            $payment->forceFill([
                'amount_total' => $capturedTotal,
                'platform_fee' => $platformShare,
                'late_fee_a' => $breakdown->lateFeeA,
                'late_fee_b' => $breakdown->lateFeeB,
                'travel_stipend_a' => $breakdown->stipendA,
                'travel_stipend_b' => $breakdown->stipendB,
                'status' => PaymentStatus::Captured,
            ])->save();

            $this->upsertTransfer($booking, $capturedTotal, $platformShare);

            return $payment->fresh('intents');
        });
    }

    public function captureForNoShowB(Booking $booking): Payment
    {
        return DB::transaction(function () use ($booking) {
            $payment = $this->ensurePreauthorized($booking)->fresh('intents');

            $baseBreakdown = $this->calculator->forBooking($booking);
            $breakdown = $this->calculator->applyLateFees($booking, $baseBreakdown);

            $legBCharge = $breakdown->bookingAmount + $breakdown->lateFeeB + $breakdown->stipendB;
            $intentA = $payment->intents->firstWhere('role', 'A');
            $captureMap = [
                'A' => $intentA?->amount_captured ?? $intentA?->amount_auth ?? 0,
                'B' => $legBCharge,
            ];

            $this->captureIntents($booking, $payment, $breakdown, $captureMap);

            $capturedTotal = $payment->intents->sum('amount_captured');
            $platformShare = $this->calculatePlatformShare($capturedTotal, $breakdown);

            $payment->forceFill([
                'amount_total' => $capturedTotal,
                'platform_fee' => $platformShare,
                'late_fee_a' => $breakdown->lateFeeA,
                'late_fee_b' => $breakdown->lateFeeB,
                'travel_stipend_a' => $breakdown->stipendA,
                'travel_stipend_b' => $breakdown->stipendB,
                'status' => PaymentStatus::Captured,
            ])->save();

            $this->upsertTransfer($booking, $capturedTotal, $platformShare);

            return $payment->fresh('intents');
        });
    }

    private function captureIntents(Booking $booking, Payment $payment, ChargeBreakdown $breakdown, array $captureMap, bool $cancelLegB = false): void
    {
        $platformFeeSplit = $this->splitAmount($breakdown->platformFee);
        $destination = $booking->operative?->stripe_connect_id;

        foreach ($payment->intents as $intent) {
            $role = strtoupper($intent->role);

            if ($cancelLegB && $role === 'B') {
                if ($intent->stripe_pi_id) {
                    $this->gateway->cancel($intent->stripe_pi_id);
                }

                $intent->forceFill([
                    'status' => PaymentIntentStatus::Canceled,
                    'amount_captured' => 0,
                ])->save();

                continue;
            }

            $amount = (int) Arr::get($captureMap, $role, $intent->amount_captured ?? $intent->amount_auth);

            if ($intent->stripe_pi_id) {
                $this->gateway->capture(
                    $intent->stripe_pi_id,
                    (int) Arr::get($platformFeeSplit, $role, 0),
                    $destination,
                    $amount
                );
            }

            $intent->forceFill([
                'status' => PaymentIntentStatus::Captured,
                'amount_captured' => $amount,
            ])->save();
        }
    }

    private function upsertTransfer(Booking $booking, int $capturedTotal, int $platformShare): void
    {
        $netToOperative = max($capturedTotal - $platformShare, 0);
        $transferId = $this->gateway->createTransfer($booking, $netToOperative);

        Transfer::query()->updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'operative_id' => $booking->operative_id,
                'amount' => $netToOperative,
                'status' => $transferId ? 'pending' : 'pending',
                'stripe_transfer_id' => $transferId,
            ]
        );
    }

    private function syncIntents(Booking $booking, Payment $payment, ChargeBreakdown $breakdown): void
    {
        $legTotals = $breakdown->legTotals();
        $platformFeeSplit = $this->splitAmount($breakdown->platformFee);
        $minCapture = $this->minimumCaptureThreshold($booking, $breakdown);
        $fullSlotPrice = $breakdown->bookingAmount + $breakdown->stipendB;

        foreach (['A', 'B'] as $leg) {
            $intent = PaymentIntent::query()->firstOrNew([
                'booking_id' => $booking->id,
                'role' => $leg,
            ]);

            $amountAuth = (int) Arr::get($legTotals, $leg, $breakdown->bookingAmount / 2);

            if ($leg === 'A') {
                $amountAuth = max($amountAuth, $minCapture);
            }

            if ($leg === 'B') {
                $amountAuth = max($amountAuth, $fullSlotPrice);
            }

            $gatewayResponse = $this->gateway->upsertPaymentIntent($booking, $leg, $amountAuth, (int) Arr::get($platformFeeSplit, $leg, 0));

            $intent->forceFill([
                'payer_id' => $this->resolvePayerId($booking, $leg),
                'stripe_pi_id' => $gatewayResponse['payment_intent_id'],
                'client_secret' => $gatewayResponse['client_secret'],
                'amount_auth' => $amountAuth,
                'app_fee_piece' => (int) Arr::get($platformFeeSplit, $leg, 0),
                'status' => PaymentIntentStatus::RequiresCapture,
            ])->save();
        }
    }

    private function resolvePayerId(Booking $booking, string $leg): int
    {
        $requestParent = $booking->slot?->request?->parent_id;

        if ($leg === 'A' && $requestParent) {
            return (int) $requestParent;
        }

        return (int) ($booking->slot?->request?->parent_id ?? $booking->operative_id);
    }

    /**
     * @return array{A: int, B: int}
     */
    private function splitAmount(int $amount): array
    {
        $half = intdiv($amount, 2);
        $remainder = $amount - ($half * 2);

        return [
            'A' => $half + $remainder,
            'B' => $half,
        ];
    }

    private function minimumCaptureThreshold(Booking $booking, ChargeBreakdown $breakdown): int
    {
        $profile = $booking->countryProfile();
        $percent = (int) ($profile->min_capture_pct_no_show ?? $this->flags->minimumCapturePercentOnNoShow());

        return (int) round($breakdown->bookingAmount * max($percent, 0) / 100);
    }

    private function calculatePlatformShare(int $capturedTotal, ChargeBreakdown $breakdown): int
    {
        if ($capturedTotal <= 0 || $breakdown->bookingAmount <= 0) {
            return 0;
        }

        $ratio = $breakdown->platformFee / $breakdown->bookingAmount;

        return (int) round($capturedTotal * $ratio);
    }
}