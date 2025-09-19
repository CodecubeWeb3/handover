<?php

namespace App\Domain\Payments\Services;

use App\Models\Booking;
use App\Models\PaymentIntent;
use App\Models\Transfer;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Str;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripePaymentGateway
{
    private bool $enabled;

    private ?StripeClient $client = null;

    public function __construct(private readonly ConfigRepository $config)
    {
        $secret = $config->get('services.stripe.secret');
        $this->enabled = $config->get('payments.gateway', 'simulation') === 'stripe' && ! empty($secret);

        if ($this->enabled) {
            $this->client = new StripeClient($secret);
        }
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return array{payment_intent_id: string, client_secret: string|null}
     */
    public function upsertPaymentIntent(Booking $booking, string $role, int $amount, int $applicationFee = 0): array
    {
        $existing = PaymentIntent::query()
            ->where('booking_id', $booking->id)
            ->where('role', $role)
            ->first();

        if (! $this->enabled) {
            $id = $existing?->stripe_pi_id ?? $this->fakeId('pi');

            return [
                'payment_intent_id' => $id,
                'client_secret' => $existing?->client_secret ?? 'pi_secret_'.$id,
            ];
        }

        $customer = $booking->slot?->request?->parent?->stripe_customer_id;
        $currency = $this->config->get('payments.currency', 'GBP');

        try {
            if ($existing && $existing->stripe_pi_id) {
                $pi = $this->client->paymentIntents->update($existing->stripe_pi_id, [
                    'amount' => $amount,
                    'currency' => $currency,
                    'metadata' => $this->intentMetadata($booking, $role),
                ]);
            } else {
                $payload = [
                    'amount' => $amount,
                    'currency' => $currency,
                    'capture_method' => 'manual',
                    'confirmation_method' => 'automatic',
                    'metadata' => $this->intentMetadata($booking, $role),
                ];

                if ($customer) {
                    $payload['customer'] = $customer;
                }

                $pi = $this->client->paymentIntents->create($payload);
            }
        } catch (ApiErrorException $exception) {
            throw new \RuntimeException('Stripe payment intent error: '.$exception->getMessage(), previous: $exception);
        }

        return [
            'payment_intent_id' => $pi->id,
            'client_secret' => $pi->client_secret ?? null,
        ];
    }

    public function capture(string $paymentIntentId, int $applicationFee = 0, ?string $destination = null, ?int $amount = null): void
    {
        if (! $this->enabled) {
            return;
        }

        $params = [];

        if ($applicationFee > 0) {
            $params['application_fee_amount'] = $applicationFee;
        }

        if ($destination) {
            $params['transfer_data'] = ['destination' => $destination];
        }

        if ($amount !== null) {
            $params['amount_to_capture'] = $amount;
        }

        try {
            $this->client->paymentIntents->capture($paymentIntentId, $params);
        } catch (ApiErrorException $exception) {
            throw new \RuntimeException('Stripe capture failed: '.$exception->getMessage(), previous: $exception);
        }
    }

    public function cancel(string $paymentIntentId, string $reason = 'requested_by_customer'): void
    {
        if (! $this->enabled) {
            return;
        }

        try {
            $this->client->paymentIntents->cancel($paymentIntentId, ['cancellation_reason' => $reason]);
        } catch (ApiErrorException $exception) {
            throw new \RuntimeException('Stripe cancellation failed: '.$exception->getMessage(), previous: $exception);
        }
    }

    public function refund(string $paymentIntentId, int $amountMinor, string $reason = 'requested_by_customer'): string
    {
        if (! $this->enabled) {
            return $this->fakeId('re');
        }

        try {
            $refund = $this->client->refunds->create([
                'payment_intent' => $paymentIntentId,
                'amount' => $amountMinor,
                'reason' => $reason,
            ]);

            return $refund->id;
        } catch (ApiErrorException $exception) {
            throw new \RuntimeException('Stripe refund failed: '.$exception->getMessage(), previous: $exception);
        }
    }

    public function createTransfer(Booking $booking, int $amountMinor): ?string
    {
        if (! $this->enabled) {
            return $amountMinor > 0 ? $this->fakeId('tr') : null;
        }

        $destination = $booking->operative?->stripe_connect_id;

        if (! $destination || $amountMinor <= 0) {
            return null;
        }

        try {
            $transfer = $this->client->transfers->create([
                'amount' => $amountMinor,
                'currency' => $this->config->get('payments.currency', 'GBP'),
                'destination' => $destination,
                'metadata' => [
                    'booking_id' => $booking->id,
                ],
            ]);

            return $transfer->id;
        } catch (ApiErrorException $exception) {
            throw new \RuntimeException('Stripe transfer failed: '.$exception->getMessage(), previous: $exception);
        }
    }

    private function intentMetadata(Booking $booking, string $role): array
    {
        return [
            'booking_id' => (string) $booking->id,
            'booking_leg' => strtoupper($role),
        ];
    }

    private function fakeId(string $prefix): string
    {
        return $prefix.'_'.Str::random(24);
    }
}
