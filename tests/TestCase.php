<?php

namespace Tests;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    private ?string $apiToken = null;

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    public function json($method, $uri, array $data = [], array $headers = [], $options = 0): TestResponse
    {
        if ($this->shouldAttachApiToken((string) $uri, $headers)) {
            $headers['Authorization'] = 'Bearer '.$this->apiToken();
        }

        return parent::json($method, $uri, $data, $headers, $options);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function shouldAttachApiToken(string $uri, array $headers): bool
    {
        if (! str_starts_with($uri, '/api/')) {
            return false;
        }

        if (array_key_exists('Authorization', $headers) || array_key_exists('Authorization', $this->defaultHeaders)) {
            return false;
        }

        return ! in_array($uri, ['/api/health', '/api/auth/login'], true);
    }

    private function apiToken(): string
    {
        if ($this->apiToken !== null) {
            return $this->apiToken;
        }

        $user = User::query()->first() ?? User::factory()->create([
            'role_id' => $this->apiTestRole()->id,
            'status' => 'active',
        ]);
        $user->forceFill([
            'role_id' => $user->role_id ?? $this->apiTestRole()->id,
            'status' => 'active',
        ])->save();

        $token = $user->createToken('api-test')->plainTextToken;

        return $this->apiToken = $token;
    }

    private function apiTestRole(): Role
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'api-test-admin'],
            [
                'name' => 'API Test Admin',
                'description' => 'Full access role for API feature tests.',
            ],
        );

        $permissionIds = [];

        foreach ($this->apiTestModules() as $module) {
            foreach (['view', 'create', 'update', 'delete', 'approve'] as $action) {
                $permission = Permission::query()->firstOrCreate(
                    [
                        'module' => $module,
                        'action' => $action,
                    ],
                    ['label' => ucfirst($action).' '.str_replace('_', ' ', $module)],
                );

                $permissionIds[$permission->id] = ['access_level' => 'full'];
            }
        }

        $role->permissions()->syncWithoutDetaching($permissionIds);

        return $role;
    }

    /**
     * @return array<int, string>
     */
    private function apiTestModules(): array
    {
        return [
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
    }
}
