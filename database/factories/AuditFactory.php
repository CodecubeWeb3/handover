<?php

namespace Database\Factories;

use App\Models\Audit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditFactory extends Factory
{
    protected $model = Audit::class;

    public function definition(): array
    {
        return [
            'actor_id' => User::factory(),
            'action' => 'test-action',
            'target_type' => 'booking',
            'target_id' => fake()->numberBetween(1, 1000),
            'meta' => ['ip' => fake()->ipv4()],
            'created_at' => now(),
        ];
    }
}