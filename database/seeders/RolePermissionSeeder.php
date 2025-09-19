<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'web';

        $permissions = [
            'manage-admin',
            'view-bookings',
            'resolve-disputes',
            'manage-payments',
            'manage-settings',
            'moderate-messaging',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, $guard);
        }

        $roleToPermissions = [
            'admin' => $permissions,
            'moderator' => ['moderate-messaging', 'view-bookings', 'resolve-disputes'],
            'operative' => ['view-bookings'],
            'parent' => ['view-bookings'],
        ];

        foreach ($roleToPermissions as $role => $perms) {
            $roleModel = Role::findOrCreate($role, $guard);
            $roleModel->syncPermissions($perms);
        }
    }
}