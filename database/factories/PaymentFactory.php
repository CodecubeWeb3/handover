<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'currency' => 'GBP',
            'amount_total' => 5000,
            'refund_total' => 0,
            'platform_fee' => 500,
            'late_fee_a' => 0,
            'late_fee_b' => 0,
            'travel_stipend_a' => 0,
            'travel_stipend_b' => 0,
            'status' => 'preauthorized',
        ];
    }
}