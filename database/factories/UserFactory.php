<?php

namespace Database\Factories;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        $role = fake()->randomElement([UserRole::Parent, UserRole::Operative]);

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'phone' => fake()->unique()->e164PhoneNumber(),
            'phone_verified_at' => now(),
            'role' => $role->value,
            'country' => fake()->countryCode(),
            'dob' => fake()->dateTimeBetween('-60 years', '-18 years'),
            'password' => static::$password ??= Hash::make('Password123!'),
            'remember_token' => Str::random(10),
        ];
    }

    public function parent(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Parent->value,
        ]);
    }

    public function operative(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Operative->value,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Admin->value,
        ]);
    }

    public function moderator(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Moderator->value,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withoutPhoneVerification(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_verified_at' => null,
        ]);
    }
}