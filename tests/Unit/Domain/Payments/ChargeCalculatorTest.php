<?php

namespace Tests\Unit\Domain\Payments;

use App\Domain\Bookings\Enums\BookingEventType;
use App\Domain\Payments\Services\ChargeCalculator;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\CountryProfile;
use App\Support\FeatureFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChargeCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('payments.slot_price_minor', 5000);
        Config::set('payments.currency', 'GBP');
        Config::set('feature-flags.flags.enable_travel_stipend', true);
        app(FeatureFlags::class)->refresh();
    }

    public function test_it_builds_breakdown_with_platform_fee_and_stipend(): void
    {
        $booking = $this->makeBooking();

        $breakdown = app(ChargeCalculator::class)->forBooking($booking->fresh());

        $this->assertSame(5000, $breakdown->bookingAmount);
        $this->assertSame('GBP', $breakdown->currency);
        $this->assertSame(600, $breakdown->platformFee);
        $this->assertSame(4400, $breakdown->operativeShare);
        $this->assertSame(2500, $breakdown->legAAmount);
        $this->assertSame(2500, $breakdown->legBAmount);
        $this->assertSame(150, $breakdown->stipendA);
        $this->assertSame(150, $breakdown->stipendB);
        $this->assertSame([
            'A' => 2650,
            'B' => 2650,
        ], $breakdown->legTotals());
    }

    public function test_it_computes_late_fees_from_booking_events(): void
    {
        $booking = $this->makeBooking();
        $slotAt = $booking->slot_ts->copy();

        $booking->events()->create([
            'event_uuid' => (string) Str::uuid(),
            'event_type' => BookingEventType::ScanAOk->value,
            'payload_json' => [],
            'chain_index' => 0,
            'prev_hash' => null,
            'this_hash' => random_bytes(32),
            'created_at' => $slotAt->copy()->addMinutes(15),
        ]);

        $booking->events()->create([
            'event_uuid' => (string) Str::uuid(),
            'event_type' => BookingEventType::ScanBOk->value,
            'payload_json' => [],
            'chain_index' => 1,
            'prev_hash' => null,
            'this_hash' => random_bytes(32),
            'created_at' => $slotAt->copy()->addMinutes(25),
        ]);

        $calculator = app(ChargeCalculator::class);
        $base = $calculator->forBooking($booking->fresh());
        $breakdown = $calculator->applyLateFees($booking->fresh(), $base);

        $this->assertSame(800, $breakdown->lateFeeA);
        $this->assertSame(800, $breakdown->lateFeeB);
        $this->assertSame(['A' => 3450, 'B' => 3450], $breakdown->legTotals());
        $this->assertSame(5000 + 800 + 800 + 150 + 150, $breakdown->totalWithAdjustments());
    }

    private function makeBooking(): Booking
    {
        $booking = Booking::factory()->create([
            'slot_ts' => now()->addHours(1),
        ]);

        CountryProfile::factory()->create([
            'country' => $booking->slot->request->parent->country,
            'platform_pct' => 12,
            'travel_stipend' => 150,
            'grace_a_min' => 5,
            'wait_cap_a_min' => 20,
            'grace_b_min' => 5,
            'wait_cap_b_min' => 15,
            'late_fee_base' => 300,
            'late_fee_per_min' => 50,
        ]);

        return $booking->fresh();
    }
}
