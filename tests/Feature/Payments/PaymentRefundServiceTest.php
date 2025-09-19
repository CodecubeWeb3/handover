<?php

namespace Tests\Feature\Payments;

use App\Domain\Bookings\Enums\BookingEventType;
use App\Domain\Payments\Enums\PaymentIntentStatus;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Services\PaymentRefundService;
use App\Domain\Payments\Services\SharedPaymentManager;
use App\Domain\Payments\Services\StripePaymentGateway;
use App\Models\Booking;
use App\Models\CountryProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\Support\Fakes\FakeStripeGateway;
use Tests\TestCase;

class PaymentRefundServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('payments.slot_price_minor', 6000);
        Config::set('payments.currency', 'GBP');
        Config::set('payments.gateway', 'simulation');
        app()->instance(StripePaymentGateway::class, new FakeStripeGateway());
    }

    public function test_refund_marks_leg_as_refunded(): void
    {
        $booking = $this->createCapturedBooking();

        $payment = app(PaymentRefundService::class)->refund($booking->fresh(), ['A'], 0, 'test_refund');

        $intentA = $payment->intents->firstWhere('role', 'A');
        $intentB = $payment->intents->firstWhere('role', 'B');

        $this->assertSame(PaymentIntentStatus::Refunded, $intentA->status);
        $this->assertSame(0, $intentA->amount_captured);
        $this->assertSame(PaymentIntentStatus::Captured, $intentB->status);
        $this->assertTrue(in_array($payment->status, [PaymentStatus::Refunded, PaymentStatus::Captured], true));
        $this->assertNotNull($payment->refunded_at);
        $this->assertSame('test_refund', $payment->refund_reason);
        $this->assertGreaterThan(0, $payment->refund_total);
    }

    public function test_mark_payout_settled_updates_transfer(): void
    {
        $booking = $this->createCapturedBooking();
        $payment = $booking->payments()->first();

        $settled = app(PaymentRefundService::class)->markPayoutSettled($payment);

        $this->assertNotNull($settled->payout_settled_at);
        $this->assertTrue(in_array($settled->status, [PaymentStatus::PayoutSettled, PaymentStatus::Refunded], true));
        $this->assertNotNull($settled->booking->transfer?->settled_at);
    }

    private function createCapturedBooking(): Booking
    {
        $booking = Booking::factory()->create([
            'slot_ts' => now()->addHours(2),
        ]);

        CountryProfile::factory()->create([
            'country' => $booking->slot->request->parent->country,
            'platform_pct' => 10,
            'travel_stipend' => 200,
            'grace_a_min' => 5,
            'wait_cap_a_min' => 20,
            'grace_b_min' => 5,
            'wait_cap_b_min' => 20,
            'late_fee_base' => 300,
            'late_fee_per_min' => 50,
            'min_capture_pct_no_show' => 50,
        ]);

        $manager = app(SharedPaymentManager::class);
        $manager->ensurePreauthorized($booking->fresh());

        $slotAt = $booking->slot_ts->copy();

        $booking->events()->create([
            'event_uuid' => (string) Str::uuid(),
            'event_type' => BookingEventType::ScanAOk->value,
            'payload_json' => [],
            'chain_index' => 0,
            'prev_hash' => null,
            'this_hash' => random_bytes(32),
            'created_at' => $slotAt->copy()->addMinutes(10),
        ]);

        $booking->events()->create([
            'event_uuid' => (string) Str::uuid(),
            'event_type' => BookingEventType::ScanBOk->value,
            'payload_json' => [],
            'chain_index' => 1,
            'prev_hash' => null,
            'this_hash' => random_bytes(32),
            'created_at' => $slotAt->copy()->addMinutes(20),
        ]);

        $manager->captureForCompletion($booking->fresh());

        return $booking->fresh();
    }
}