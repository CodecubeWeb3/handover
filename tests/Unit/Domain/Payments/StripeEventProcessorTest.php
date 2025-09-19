<?php

namespace Tests\Unit\Domain\Payments;

use App\Domain\Payments\Services\StripeEventProcessor;
use App\Domain\Payments\Services\StripePaymentGateway;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\Transfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Stripe\Event as StripeEvent;
use Tests\Support\Fakes\FakeStripeGateway;
use Tests\TestCase;

class StripeEventProcessorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('payments.gateway', 'simulation');
        app()->instance(StripePaymentGateway::class, new FakeStripeGateway());
    }

    public function test_payment_intent_succeeded_updates_intent_and_payment(): void
    {
        $booking = Booking::factory()->create();
        $payment = Payment::factory()->create([
            'booking_id' => $booking->id,
            'status' => 'preauthorized',
        ]);

        $intent = PaymentIntent::factory()->create([
            'booking_id' => $booking->id,
            'stripe_pi_id' => 'pi_test',
            'status' => 'requires_capture',
            'amount_captured' => 0,
        ]);

        $event = StripeEvent::constructFrom([
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test',
                    'amount_received' => 6000,
                    'status' => 'succeeded',
                ],
            ],
        ]);

        app(StripeEventProcessor::class)->handle($event);

        $intent->refresh();
        $payment->refresh();

        $this->assertSame('captured', $intent->status->value);
        $this->assertSame(6000, $intent->amount_captured);
        $this->assertSame('captured', $payment->status->value);
    }

    public function test_transfer_paid_marks_transfer_and_payment(): void
    {
        $booking = Booking::factory()->create();
        $payment = Payment::factory()->create([
            'booking_id' => $booking->id,
            'status' => 'captured',
        ]);

        Transfer::factory()->create([
            'booking_id' => $booking->id,
            'stripe_transfer_id' => 'tr_test',
            'status' => 'pending',
            'settled_at' => null,
        ]);

        $event = StripeEvent::constructFrom([
            'type' => 'transfer.paid',
            'data' => [
                'object' => [
                    'id' => 'tr_test',
                ],
            ],
        ]);

        app(StripeEventProcessor::class)->handle($event);

        $payment->refresh();
        $transfer = $payment->booking->transfer;

        $this->assertSame('paid', $transfer->status);
        $this->assertNotNull($transfer->settled_at);
        $this->assertNotNull($payment->payout_settled_at);
    }
}