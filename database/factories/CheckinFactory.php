<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Checkin;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CheckinFactory extends Factory
{
    protected $model = Checkin::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'user_id' => User::factory(),
            'kind' => 'A_SCAN',
            'lat' => fake()->latitude(),
            'lng' => fake()->longitude(),
            'accuracy_m' => fake()->numberBetween(5, 20),
            'token_id' => fake()->uuid(),
            'device_attested' => true,
            'note' => null,
            'created_at' => now(),
        ];
    }
}