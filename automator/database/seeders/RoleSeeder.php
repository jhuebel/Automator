<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Permissions granted to each role. Admin implicitly gets everything.
     */
    private const ROLE_PERMISSIONS = [
        'Admin' => [
            'scripts.view', 'scripts.run', 'scripts.edit', 'scripts.delete',
            'jobs.view', 'jobs.manage', 'settings.manage', 'users.manage', 'audit.view',
        ],
        'Developer' => [
            'scripts.view', 'scripts.run', 'scripts.edit', 'scripts.delete',
            'jobs.view', 'jobs.manage',
        ],
        'Operator' => [
            'scripts.view', 'scripts.run', 'jobs.view',
        ],
        'Viewer' => [
            'scripts.view', 'jobs.view',
        ],
    ];

    public function run(): void
    {
        $permissions = collect(self::ROLE_PERMISSIONS)->flatten()->unique();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLE_PERMISSIONS as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName);
            $role->syncPermissions($rolePermissions);
        }
    }
}
