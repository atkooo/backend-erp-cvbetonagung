<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    private const MODULES = [
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

    /**
     * @var array<string, string>
     */
    private const ACTIONS = [
        'view' => 'View',
        'create' => 'Create',
        'update' => 'Update',
        'delete' => 'Delete',
        'approve' => 'Approve',
    ];

    /**
     * @var array<string, array{name: string, description: string, modules: array<string, string>}>
     */
    private const ROLES = [
        'admin' => [
            'name' => 'Administrator',
            'description' => 'Full access to ERP backend modules.',
            'modules' => [
                '*' => 'full',
            ],
        ],
        'finance' => [
            'name' => 'Finance',
            'description' => 'Finance access for invoices, payments, payables, reports, and related master data.',
            'modules' => [
                'customers' => 'read',
                'suppliers' => 'read',
                'products' => 'read',
                'sales' => 'read',
                'purchasing' => 'read',
                'projects' => 'read',
                'finance' => 'full',
                'reports' => 'read',
            ],
        ],
        'sales' => [
            'name' => 'Sales',
            'description' => 'Sales access for customers, quotations, sales orders, and delivery workflow.',
            'modules' => [
                'customers' => 'edit',
                'products' => 'read',
                'inventory' => 'read',
                'sales' => 'full',
                'projects' => 'read',
                'finance' => 'read',
                'reports' => 'read',
            ],
        ],
        'inventory' => [
            'name' => 'Inventory',
            'description' => 'Inventory access for stock, warehouses, movements, opname, and approvals.',
            'modules' => [
                'suppliers' => 'read',
                'products' => 'read',
                'inventory' => 'full',
                'purchasing' => 'read',
                'sales' => 'read',
                'approvals' => 'full',
                'reports' => 'read',
            ],
        ],
        'purchasing' => [
            'name' => 'Purchasing',
            'description' => 'Purchasing access for suppliers, purchase orders, receiving, and returns.',
            'modules' => [
                'suppliers' => 'edit',
                'products' => 'read',
                'inventory' => 'read',
                'purchasing' => 'full',
                'finance' => 'read',
                'reports' => 'read',
            ],
        ],
        'project' => [
            'name' => 'Project',
            'description' => 'Project access for project tracking, timelines, documents, and budgets.',
            'modules' => [
                'customers' => 'read',
                'products' => 'read',
                'sales' => 'read',
                'projects' => 'full',
                'finance' => 'read',
                'reports' => 'read',
            ],
        ],
        'production' => [
            'name' => 'Production',
            'description' => 'Production access for work orders, work logs, BOM, and stock visibility.',
            'modules' => [
                'products' => 'read',
                'inventory' => 'read',
                'projects' => 'read',
                'production' => 'full',
                'reports' => 'read',
            ],
        ],
        'viewer' => [
            'name' => 'Viewer',
            'description' => 'Read-only ERP access for management monitoring.',
            'modules' => [
                '*' => 'read',
            ],
        ],
    ];

    /**
     * @var array<int, array{role: string, name: string, email: string}>
     */
    private const USERS = [
        [
            'role' => 'admin',
            'name' => 'System Administrator',
            'email' => 'admin@example.com',
        ],
        [
            'role' => 'finance',
            'name' => 'Finance User',
            'email' => 'finance@example.com',
        ],
        [
            'role' => 'sales',
            'name' => 'Sales User',
            'email' => 'sales@example.com',
        ],
        [
            'role' => 'inventory',
            'name' => 'Inventory User',
            'email' => 'inventory@example.com',
        ],
        [
            'role' => 'purchasing',
            'name' => 'Purchasing User',
            'email' => 'purchasing@example.com',
        ],
        [
            'role' => 'project',
            'name' => 'Project User',
            'email' => 'project@example.com',
        ],
        [
            'role' => 'production',
            'name' => 'Production User',
            'email' => 'production@example.com',
        ],
        [
            'role' => 'viewer',
            'name' => 'Management Viewer',
            'email' => 'viewer@example.com',
        ],
    ];

    /**
     * Seed roles, permissions, and demo login users.
     */
    public function run(): void
    {
        $permissions = $this->seedPermissions();
        $roles = $this->seedRoles($permissions);

        $this->seedUsers($roles);
    }

    /**
     * @return array<string, array<string, Permission>>
     */
    private function seedPermissions(): array
    {
        $permissions = [];

        foreach (self::MODULES as $module) {
            foreach (self::ACTIONS as $action => $label) {
                $permissions[$module][$action] = Permission::query()->updateOrCreate(
                    [
                        'module' => $module,
                        'action' => $action,
                    ],
                    ['label' => $label.' '.str_replace('_', ' ', $module)],
                );
            }
        }

        return $permissions;
    }

    /**
     * @param array<string, array<string, Permission>> $permissions
     * @return array<string, Role>
     */
    private function seedRoles(array $permissions): array
    {
        $roles = [];

        foreach (self::ROLES as $code => $roleConfig) {
            $role = Role::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $roleConfig['name'],
                    'description' => $roleConfig['description'],
                ],
            );

            $role->permissions()->sync($this->permissionPivot($permissions, $roleConfig['modules']));
            $roles[$code] = $role;
        }

        return $roles;
    }

    /**
     * @param array<string, array<string, Permission>> $permissions
     * @param array<string, string> $moduleAccess
     * @return array<string, array{access_level: string}>
     */
    private function permissionPivot(array $permissions, array $moduleAccess): array
    {
        $pivot = [];

        foreach ($moduleAccess as $module => $accessLevel) {
            $modules = $module === '*' ? self::MODULES : [$module];

            foreach ($modules as $moduleName) {
                foreach (array_keys(self::ACTIONS) as $action) {
                    $permission = $permissions[$moduleName][$action] ?? null;

                    if ($permission === null) {
                        continue;
                    }

                    $pivot[$permission->id] = ['access_level' => $accessLevel];
                }
            }
        }

        return $pivot;
    }

    /**
     * @param array<string, Role> $roles
     */
    private function seedUsers(array $roles): void
    {
        foreach (self::USERS as $userConfig) {
            $role = $roles[$userConfig['role']] ?? null;

            if ($role === null) {
                continue;
            }

            User::query()->updateOrCreate(
                ['email' => $userConfig['email']],
                [
                    'role_id' => $role->id,
                    'name' => $userConfig['name'],
                    'password' => 'password',
                    'status' => 'active',
                ],
            );
        }
    }
}
