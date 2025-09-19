<?php

namespace Tests\Feature\Payments;

use App\Domain\Bookings\Enums\BookingEventType;
use App\Domain\Payments\Enums\PaymentIntentStatus;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Services\ChargeCalculator;
use App\Domain\Payments\Services\SharedPaymentManager;
use App\Domain\Payments\Services\StripePaymentGateway;
use App\Models\Booking;
use App\Models\CountryProfile;
use App\Models\Transfer;
use App\Support\FeatureFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\Support\Fakes\FakeStripeGateway;
use Tests\TestCase;

class SharedPaymentManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('payments.slot_price_minor', 6000);
        Config::set('payments.currency', 'GBP');
        Config::set('payments.gateway', 'simulation');
        Config::set('feature-flags.flags.enable_travel_stipend', true);
        app()->instance(StripePaymentGateway::class, new FakeStripeGateway());
        app(FeatureFlags::class)->refresh();
    }

    public function test_it_creates_payment_and_intents_for_booking(): void
    {
        $booking = $this->createBookingWithProfile();
        $breakdown = app(ChargeCalculator::class)->forBooking($booking);

        $payment = app(SharedPaymentManager::class)->ensurePreauthorized($booking->fresh());

        $this->assertSame(PaymentStatus::Preauthorized, $payment->status);
        $this->assertCount(2, $payment->intents);

        $intentA = $payment->intents->firstWhere('role', 'A');
        $intentB = $payment->intents->firstWhere('role', 'B');

        $this->assertNotNull($intentA);
        $this->assertNotNull($intentB);

        $minThreshold = (int) round($breakdown->bookingAmount * app(FeatureFlags::class)->minimumCapturePercentOnNoShow() / 100);
        $this->assertGreaterThanOrEqual($minThreshold, $intentA->amount_auth);
        $this->assertGreaterThanOrEqual($breakdown->bookingAmount + $breakdown->stipendB, $intentB->amount_auth);
    }

    public function test_it_captures_payment_and_creates_transfer_with_late_fees(): void
    {
        $booking = $this->createBookingWithProfile();
        $slotAt = $booking->slot_ts->copy();

        $booking->events()->create([
            'event_uuid' => (string) Str::uuid(),
            'event_type' => BookingEventType::ScanAOk->value,
            'payload_json' => [],
            'chain_index' => 0,
            'prev_hash' => null,
            'this_hash' => random_bytes(32),
            'created_at' => $slotAt->copy()->addMinutes(18),
        ]);

        $booking->events()->create([
            'event_uuid' => (string) Str::uuid(),
            'event_type' => BookingEventType::ScanBOk->value,
            'payload_json' => [],
            'chain_index' => 1,
            'prev_hash' => null,
            'this_hash' => random_bytes(32),
            'created_at' => $slotAt->copy()->addMinutes(28),
        ]);

        $manager = app(SharedPaymentManager::class);
        $manager->ensurePreauthorized($booking->fresh());

        $captured = $manager->captureForCompletion($booking->fresh());
        $this->assertSame(PaymentStatus::Captured, $captured->status);

        $calculator = app(ChargeCalculator::class);
        $breakdown = $calculator->applyLateFees($booking->fresh(), $calculator->forBooking($booking->fresh()));
        $expectedTotals = $breakdown->legTotals();

        foreach ($captured->intents as $intent) {
            $this->assertSame(PaymentIntentStatus::Captured, $intent->status);
            $this->assertSame(Arr::get($expectedTotals, strtoupper($intent->role)), $intent->amount_captured);
        }

        $capturedTotal = array_sum($expectedTotals);
        $transfer = Transfer::query()->where('booking_id', $booking->id)->first();
        $this->assertNotNull($transfer);
        $this->assertSame('pending', $transfer->status);
        $this->assertLessThanOrEqual($capturedTotal, $transfer->amount);
    }

    public function test_it_handles_no_show_a(): void
    {
        $booking = $this->createBookingWithProfile();
        $manager = app(SharedPaymentManager::class);
        $manager->ensurePreauthorized($booking->fresh());

        $payment = $manager->captureForNoShowA($booking->fresh());
        $breakdown = app(ChargeCalculator::class)->forBooking($booking->fresh());
        $minThreshold = (int) round($breakdown->bookingAmount * app(FeatureFlags::class)->minimumCapturePercentOnNoShow() / 100);
        $expectedCapture = max($minThreshold, $breakdown->lateFeeA + $breakdown->stipendA);

        $intentA = $payment->intents->firstWhere('role', 'A');
        $intentB = $payment->intents->firstWhere('role', 'B');

        $this->assertSame(PaymentIntentStatus::Captured, $intentA->status);
        $this->assertSame($expectedCapture, $intentA->amount_captured);
        $this->assertSame(PaymentIntentStatus::Canceled, $intentB->status);
        $this->assertSame(0, $intentB->amount_captured);
    }

    public function test_it_handles_no_show_b(): void
    {
        $booking = $this->createBookingWithProfile();
        $manager = app(SharedPaymentManager::class);
        $manager->ensurePreauthorized($booking->fresh());

        $payment = $manager->captureForNoShowB($booking->fresh());
        $breakdown = app(ChargeCalculator::class)->forBooking($booking->fresh());
        $expectedB = $breakdown->bookingAmount + $breakdown->lateFeeB + $breakdown->stipendB;

        $intentB = $payment->intents->firstWhere('role', 'B');
        $this->assertSame(PaymentIntentStatus::Captured, $intentB->status);
        $this->assertSame($expectedB, $intentB->amount_captured);
    }

    private function createBookingWithProfile(): Booking
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

        return $booking->fresh();
    }
}