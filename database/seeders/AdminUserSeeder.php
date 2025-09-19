<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@safe-handover.local'],
            [
                'name' => 'Safe Handover Admin',
                'password' => Hash::make('ChangeMe123!'),
                'role' => UserRole::Admin,
                'country' => 'GB',
                'phone' => '+441234567890',
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole('admin');
    }
}