<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecSalesReportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_fetch_gross_profit_report(): void
    {
        $role = Role::query()->create([
            'code' => 'admin',
            'name' => 'Admin',
        ]);

        $permission = Permission::query()->create([
            'module' => 'reports',
            'action' => 'view',
            'label' => 'View Reports',
        ]);

        $role->permissions()->attach($permission->id, ['access_level' => 'full']);

        $user = User::query()->create([
            'name' => 'Admin Test',
            'email' => 'admintest@example.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/reports/exec/gross-profit?period=daily&date_from=2026-07-01&date_to=2026-07-23');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'revenue',
                        'cogs',
                        'gross_profit',
                        'margin_pct',
                    ],
                    'by_category',
                ],
            ]);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $role = Role::query()->create([
            'code' => 'no_permission',
            'name' => 'No Permission',
        ]);

        $user = User::query()->create([
            'name' => 'Restricted User',
            'email' => 'restricted@example.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/reports/exec/gross-profit');

        $response->assertForbidden();
    }
}
