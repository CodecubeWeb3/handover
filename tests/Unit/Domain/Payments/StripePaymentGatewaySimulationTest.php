<?php

namespace Tests\Unit\Domain\Payments;

use App\Domain\Payments\Services\StripePaymentGateway;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class StripePaymentGatewaySimulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulation_generates_deterministic_identifiers(): void
    {
        Config::set('payments.gateway', 'simulation');
        Config::set('services.stripe.secret', null);

        $booking = Booking::factory()->create();
        $gateway = app(StripePaymentGateway::class);

        $intent = $gateway->upsertPaymentIntent($booking, 'A', 1000);
        $this->assertSame("pi_sim_{$booking->id}_a", $intent['payment_intent_id']);
        $this->assertSame("pi_secret_{$intent['payment_intent_id']}", $intent['client_secret']);

        $refund = $gateway->refund($intent['payment_intent_id'], 500);
        $this->assertSame("re_sim_pi_sim_{$booking->id}_a_500", $refund);

        $transfer = $gateway->createTransfer($booking, 1500);
        $this->assertSame("tr_sim_{$booking->id}_1500", $transfer);
    }
}
