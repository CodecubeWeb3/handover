<?php

namespace App\Domain\Payments\Services;

use App\Domain\Bookings\Enums\BookingEventType;
use App\Domain\Payments\Data\ChargeBreakdown;
use App\Models\Booking;
use App\Support\FeatureFlags;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class ChargeCalculator
{
    public function __construct(private readonly FeatureFlags $flags)
    {
    }

    public function forBooking(Booking $booking): ChargeBreakdown
    {
        $profile = $booking->countryProfile();
        $currency = Config::get('payments.currency', 'GBP');
        $baseAmount = (int) Config::get('payments.slot_price_minor', 4000);

        $platformPct = (int) ($profile->platform_pct ?? $this->flags->platformSharePercent());
        $platformFee = (int) round($baseAmount * max($platformPct, 0) / 100);
        $operativeShare = max($baseAmount - $platformFee, 0);

        $half = intdiv($baseAmount, 2);
        $remainder = $baseAmount - ($half * 2);
        $legAAmount = $half + $remainder;
        $legBAmount = $half;

        $stipend = 0;

        if ($this->flags->travelStipendEnabled() && isset($profile->travel_stipend)) {
            $stipend = (int) $profile->travel_stipend;
        }

        return new ChargeBreakdown(
            bookingAmount: $baseAmount,
            currency: $currency,
            platformFee: $platformFee,
            operativeShare: $operativeShare,
            legAAmount: $legAAmount,
            legBAmount: $legBAmount,
            lateFeeA: 0,
            lateFeeB: 0,
            stipendA: $stipend,
            stipendB: $stipend,
        );
    }

    public function applyLateFees(Booking $booking, ChargeBreakdown $breakdown): ChargeBreakdown
    {
        if (! $booking->slot_ts) {
            return $breakdown;
        }

        $profile = $booking->countryProfile();
        $slotAt = Carbon::parse($booking->slot_ts);

        $scanAEvent = $booking->events()
            ->where('event_type', BookingEventType::ScanAOk->value)
            ->latest('created_at')
            ->first();

        $scanBEvent = $booking->events()
            ->where('event_type', BookingEventType::ScanBOk->value)
            ->latest('created_at')
            ->first();

        $lateFeeA = $this->computeLateFee(
            slotAt: $slotAt,
            eventAt: $scanAEvent?->created_at,
            grace: (int) ($profile->grace_a_min ?? 0),
            waitCap: (int) ($profile->wait_cap_a_min ?? 0),
            base: (int) ($profile->late_fee_base ?? $this->flags->lateFeeBaseMinor()),
            perMinute: (int) ($profile->late_fee_per_min ?? $this->flags->lateFeePerMinuteMinor()),
        );

        $lateFeeB = $this->computeLateFee(
            slotAt: $slotAt,
            eventAt: $scanBEvent?->created_at,
            grace: (int) ($profile->grace_b_min ?? 0),
            waitCap: (int) ($profile->wait_cap_b_min ?? 0),
            base: (int) ($profile->late_fee_base ?? $this->flags->lateFeeBaseMinor()),
            perMinute: (int) ($profile->late_fee_per_min ?? $this->flags->lateFeePerMinuteMinor()),
        );

        return $breakdown->withLateFees($lateFeeA, $lateFeeB);
    }

    private function computeLateFee(
        Carbon $slotAt,
        ?Carbon $eventAt,
        int $grace,
        int $waitCap,
        int $base,
        int $perMinute,
    ): int {
        if ($eventAt === null) {
            return 0;
        }

        $delayMinutes = max(0, $slotAt->diffInMinutes($eventAt, false));

        if ($delayMinutes <= $grace) {
            return 0;
        }

        $maxWindow = max($waitCap - $grace, 0);
        $lateMinutes = min($delayMinutes - $grace, $maxWindow ?: $delayMinutes - $grace);

        if ($lateMinutes <= 0) {
            return 0;
        }

        return max(0, $base) + ($lateMinutes * max($perMinute, 0));
    }
}