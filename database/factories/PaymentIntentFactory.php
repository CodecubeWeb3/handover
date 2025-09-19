<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\PaymentIntent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentIntentFactory extends Factory
{
    protected $model = PaymentIntent::class;

    public function definition(): array
    {
        $stripeId = 'pi_'.fake()->lexify('????????????');

        return [
            'booking_id' => Booking::factory(),
            'payer_id' => User::factory()->parent(),
            'role' => fake()->randomElement(['A', 'B']),
            'stripe_pi_id' => $stripeId,
            'client_secret' => 'pi_secret_'.$stripeId,
            'amount_auth' => 5000,
            'amount_captured' => 0,
            'app_fee_piece' => 500,
            'status' => 'requires_capture',
            'last_status' => null,
            'last_error' => null,
        ];
    }
}