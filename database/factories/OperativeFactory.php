<?php

namespace Database\Factories;

use App\Models\Operative;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OperativeFactory extends Factory
{
    protected $model = Operative::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->operative(),
            'kyc_status' => 'unverified',
            'reliability_score' => fake()->randomFloat(2, 3, 5),
            'stripe_connect_id' => null,
            'languages' => fake()->randomElements(['en', 'es', 'fr', 'de'], 2),
            'bio' => fake()->sentence(10),
        ];
    }
}