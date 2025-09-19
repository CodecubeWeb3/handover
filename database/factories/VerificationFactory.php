<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Verification;
use Illuminate\Database\Eloquent\Factories\Factory;

class VerificationFactory extends Factory
{
    protected $model = Verification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'stripe_identity',
            'provider_ref' => 'vs_'.fake()->lexify('????????'),
            'result' => 'pending',
        ];
    }
}