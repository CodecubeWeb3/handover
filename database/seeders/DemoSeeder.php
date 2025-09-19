<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $parents = User::factory()->count(2)->state([
            'role' => UserRole::Parent,
            'password' => 'Password123!'
        ])->create();

        $operatives = User::factory()->count(2)->state([
            'role' => UserRole::Operative,
            'password' => 'Password123!'
        ])->create();

        $this->command?->info('Demo users seeded: '.($parents->count() + $operatives->count()));
    }
}