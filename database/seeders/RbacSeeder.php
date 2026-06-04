<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    /**
     * Seed roles, permissions, and the first admin user.
     */
    public function run(): void
    {
        $adminRole = Role::query()->updateOrCreate(
            ['code' => 'admin'],
            [
                'name' => 'Administrator',
                'description' => 'Full access to ERP backend modules.',
            ],
        );

        $modules = [
            'users',
            'roles',
            'employees',
            'customers',
            'suppliers',
            'products',
            'inventory',
            'sales',
            'purchasing',
            'projects',
            'finance',
            'production',
            'approvals',
            'reports',
            'settings',
        ];

        $actions = [
            'view' => 'View',
            'create' => 'Create',
            'update' => 'Update',
            'delete' => 'Delete',
            'approve' => 'Approve',
        ];

        $permissionIds = [];

        foreach ($modules as $module) {
            foreach ($actions as $action => $label) {
                $permission = Permission::query()->updateOrCreate(
                    [
                        'module' => $module,
                        'action' => $action,
                    ],
                    ['label' => $label.' '.str_replace('_', ' ', $module)],
                );

                $permissionIds[$permission->id] = ['access_level' => 'full'];
            }
        }

        $adminRole->permissions()->sync($permissionIds);

        User::factory()->create([
            'role_id' => $adminRole->id,
            'name' => 'System Administrator',
            'email' => 'admin@example.com',
            'status' => 'active',
        ]);
    }
}
