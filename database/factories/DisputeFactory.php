<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Dispute;
use Illuminate\Database\Eloquent\Factories\Factory;

class DisputeFactory extends Factory
{
    protected $model = Dispute::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'reason' => fake()->sentence(4),
            'evidence_uri' => null,
            'resolution' => 'pending',
        ];
    }
}