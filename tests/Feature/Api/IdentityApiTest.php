<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdentityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_permission_and_pivot_access_can_be_created_and_updated(): void
    {
        $roleResponse = $this->postJson('/api/identity/roles', [
            'code' => 'api-role',
            'name' => 'API Role',
            'description' => 'Role created from API test.',
        ]);

        $roleResponse
            ->assertCreated()
            ->assertJsonPath('data.code', 'api-role');

        $permissionResponse = $this->postJson('/api/identity/permissions', [
            'module' => 'api-module',
            'action' => 'view',
            'label' => 'View API Module',
        ]);

        $permissionResponse
            ->assertCreated()
            ->assertJsonPath('data.module', 'api-module');

        $roleId = $roleResponse->json('data.id');
        $permissionId = $permissionResponse->json('data.id');

        $this->postJson('/api/identity/role-permissions', [
            'role_id' => $roleId,
            'permission_id' => $permissionId,
            'access_level' => 'read',
        ])
            ->assertCreated()
            ->assertJsonPath('data.role.code', 'api-role')
            ->assertJsonPath('data.permission.module', 'api-module')
            ->assertJsonPath('data.access_level', 'read');

        $this->patchJson("/api/identity/role-permissions/{$roleId}/{$permissionId}", [
            'access_level' => 'full',
        ])
            ->assertOk()
            ->assertJsonPath('data.access_level', 'full');

        $this->getJson('/api/identity/roles?q=api-role')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_user_and_employee_can_be_created(): void
    {
        $role = Role::query()->create([
            'code' => 'staff',
            'name' => 'Staff',
        ]);

        $userResponse = $this->postJson('/api/identity/users', [
            'role_id' => $role->id,
            'name' => 'API User',
            'email' => 'api.user@example.com',
            'password' => 'password-api',
            'status' => 'active',
        ]);

        $userResponse
            ->assertCreated()
            ->assertJsonPath('data.email', 'api.user@example.com')
            ->assertJsonPath('data.role.code', 'staff')
            ->assertJsonMissingPath('data.password');

        $user = User::query()->where('email', 'api.user@example.com')->firstOrFail();

        $this->postJson('/api/identity/employees', [
            'employee_number' => 'EMP-API-001',
            'user_id' => $user->id,
            'name' => 'API Employee',
            'role_name' => 'operator',
            'department' => 'workshop',
            'join_date' => '2026-06-05',
            'employee_type' => 'daily',
            'daily_rate' => 100000,
            'piece_rate' => 0,
            'status' => 'active',
        ])
            ->assertCreated()
            ->assertJsonPath('data.employee_number', 'EMP-API-001')
            ->assertJsonPath('data.user.email', 'api.user@example.com');
    }

    public function test_identity_api_rejects_invalid_access_level(): void
    {
        $role = Role::query()->create([
            'code' => 'bad-access-role',
            'name' => 'Bad Access Role',
        ]);
        $permission = Permission::query()->create([
            'module' => 'bad-access',
            'action' => 'view',
        ]);

        $this->postJson('/api/identity/role-permissions', [
            'role_id' => $role->id,
            'permission_id' => $permission->id,
            'access_level' => 'admin',
        ])->assertUnprocessable();
    }
}
