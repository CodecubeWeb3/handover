<?php

namespace Tests\Support\Fakes;

use App\Domain\Payments\Services\StripePaymentGateway;
use App\Models\Booking;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Str;

class FakeStripeGateway extends StripePaymentGateway
{
    private array $captures = [];

    private array $refunds = [];

    private array $transfers = [];

    public function __construct()
    {
        parent::__construct(app(ConfigRepository::class));
    }

    public function enabled(): bool
    {
        return false;
    }

    public function upsertPaymentIntent(Booking $booking, string $role, int $amount, int $applicationFee = 0): array
    {
        $id = 'pi_fake_'.$booking->id.'_'.$role;

        return [
            'payment_intent_id' => $id,
            'client_secret' => 'pi_secret_'.$id,
        ];
    }

    public function capture(string $paymentIntentId, int $applicationFee = 0, ?string $destination = null, ?int $amount = null): void
    {
        $this->captures[] = compact('paymentIntentId', 'applicationFee', 'destination', 'amount');
    }

    public function cancel(string $paymentIntentId, string $reason = 'requested_by_customer'): void
    {
        $this->captures[] = ['paymentIntentId' => $paymentIntentId, 'cancelled' => true, 'reason' => $reason];
    }

    public function refund(string $paymentIntentId, int $amountMinor, string $reason = 'requested_by_customer'): string
    {
        $id = 're_fake_'.Str::random(10);
        $this->refunds[] = compact('paymentIntentId', 'amountMinor', 'reason', 'id');

        return $id;
    }

    public function createTransfer(Booking $booking, int $amountMinor): ?string
    {
        $id = $amountMinor > 0 ? 'tr_fake_'.Str::random(10) : null;

        if ($id) {
            $this->transfers[] = compact('id', 'amountMinor');
        }

        return $id;
    }
}