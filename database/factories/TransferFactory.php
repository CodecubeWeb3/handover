<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransferFactory extends Factory
{
    protected $model = Transfer::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'operative_id' => User::factory()->operative(),
            'stripe_transfer_id' => null,
            'amount' => 4000,
            'status' => 'pending',
            'settled_at' => null,
        ];
    }
}