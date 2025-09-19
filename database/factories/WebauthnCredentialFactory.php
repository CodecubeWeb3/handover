<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WebauthnCredential;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebauthnCredentialFactory extends Factory
{
    protected $model = WebauthnCredential::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->operative(),
            'credential_id' => random_bytes(32),
            'public_key' => random_bytes(128),
            'transports' => 'internal',
            'sign_count' => 0,
            'last_used_at' => null,
        ];
    }
}