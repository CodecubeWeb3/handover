<?php

namespace App\Domain\Payments\Data;

final class ChargeBreakdown
{
    public function __construct(
        public readonly int $bookingAmount,
        public readonly string $currency,
        public readonly int $platformFee,
        public readonly int $operativeShare,
        public readonly int $legAAmount,
        public readonly int $legBAmount,
        public readonly int $lateFeeA = 0,
        public readonly int $lateFeeB = 0,
        public readonly int $stipendA = 0,
        public readonly int $stipendB = 0,
    ) {
    }

    public function withLateFees(int $lateFeeA, int $lateFeeB): self
    {
        return new self(
            bookingAmount: $this->bookingAmount,
            currency: $this->currency,
            platformFee: $this->platformFee,
            operativeShare: $this->operativeShare,
            legAAmount: $this->legAAmount,
            legBAmount: $this->legBAmount,
            lateFeeA: $lateFeeA,
            lateFeeB: $lateFeeB,
            stipendA: $this->stipendA,
            stipendB: $this->stipendB,
        );
    }

    public function totalWithAdjustments(): int
    {
        return $this->bookingAmount + $this->lateFeeA + $this->lateFeeB + $this->stipendA + $this->stipendB;
    }

    public function legTotals(): array
    {
        return [
            'A' => $this->legAAmount + $this->lateFeeA + $this->stipendA,
            'B' => $this->legBAmount + $this->lateFeeB + $this->stipendB,
        ];
    }
}