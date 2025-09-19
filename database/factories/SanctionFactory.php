<?php

namespace Database\Factories;

use App\Models\Sanction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SanctionFactory extends Factory
{
    protected $model = Sanction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['warning','strike','suspension']),
            'reason' => fake()->sentence(),
            'expires_at' => now()->addDays(30),
            'created_at' => now(),
        ];
    }
}