<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RatingFactory extends Factory
{
    protected $model = Rating::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'rater_id' => User::factory(),
            'ratee_id' => User::factory(),
            'role' => 'parent_rates_operative',
            'stars' => fake()->numberBetween(4, 5),
            'tag' => fake()->randomElement(['punctual','friendly','professional']),
            'comment' => fake()->sentence(),
            'created_at' => now(),
        ];
    }
}