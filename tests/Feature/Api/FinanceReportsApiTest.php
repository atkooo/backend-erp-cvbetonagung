<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\CashTransaction;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceReportsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->adminUser = User::query()->create([
            'name' => 'Admin Finance',
            'email' => 'adminfintest@example.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }

    public function test_user_can_fetch_cashflow_report(): void
    {
        $account = Account::query()->create([
            'code' => 'ACC-001',
            'name' => 'Kas Operasional Bank BCA',
            'type' => 'bank',
            'is_active' => true,
        ]);

        CashTransaction::query()->create([
            'transaction_number' => 'TRX-202607-001',
            'account_id' => $account->id,
            'transaction_date' => now()->format('Y-m-d'),
            'type' => 'in',
            'amount' => 150000000,
            'category' => 'Penjualan',
            'description' => 'Pembayaran DP Pelanggan',
            'recorded_by' => $this->adminUser->id,
        ]);

        CashTransaction::query()->create([
            'transaction_number' => 'TRX-202607-002',
            'account_id' => $account->id,
            'transaction_date' => now()->format('Y-m-d'),
            'type' => 'out',
            'amount' => 25000000,
            'category' => 'Bahan Baku',
            'description' => 'Pembelian Semen Gresik',
            'recorded_by' => $this->adminUser->id,
        ]);

        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->getJson('/api/reports/finance/cashflow');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'opening_balance',
                        'total_cash_in',
                        'total_cash_out',
                        'net_cash_flow',
                        'ending_balance',
                    ],
                    'rows' => [
                        '*' => [
                            'id',
                            'transaction_number',
                            'transaction_date',
                            'account_name',
                            'type',
                            'debit',
                            'credit',
                            'running_balance',
                        ],
                    ],
                ],
            ]);
    }

    public function test_user_can_fetch_expenses_report(): void
    {
        $account = Account::query()->create([
            'code' => 'ACC-002',
            'name' => 'Kas Tunai',
            'type' => 'cash',
            'is_active' => true,
        ]);

        CashTransaction::query()->create([
            'transaction_number' => 'EXP-202607-001',
            'account_id' => $account->id,
            'transaction_date' => now()->format('Y-m-d'),
            'type' => 'out',
            'amount' => 5000000,
            'category' => 'BBM & Solar Mixer',
            'description' => 'BBM Truk Mixer No. Pol B 1234 CD',
            'recorded_by' => $this->adminUser->id,
        ]);

        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->getJson('/api/reports/finance/expenses');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'total_expense_transactions',
                        'total_expenses_amount',
                        'top_expense_category',
                    ],
                    'by_category',
                    'rows',
                ],
            ]);
    }

    public function test_user_can_fetch_profit_loss_report(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->getJson('/api/reports/finance/profit-loss');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'summary' => [
                        'total_revenue',
                        'total_cogs',
                        'gross_profit',
                        'gross_margin_pct',
                        'total_operating_expenses',
                        'net_profit',
                        'net_margin_pct',
                    ],
                    'breakdown' => [
                        'revenue_items',
                        'cogs_items',
                        'expense_categories',
                    ],
                ],
            ]);
    }

    public function test_unauthorized_user_cannot_access_finance_reports(): void
    {
        $noPermRole = Role::query()->create(['code' => 'no_perm', 'name' => 'No Perm']);
        $restrictedUser = User::query()->create([
            'name' => 'Restricted User',
            'email' => 'restricted_fin@example.com',
            'password' => bcrypt('password'),
            'role_id' => $noPermRole->id,
            'status' => 'active',
        ]);

        $this->actingAs($restrictedUser, 'sanctum');

        $this->getJson('/api/reports/finance/cashflow')->assertForbidden();
        $this->getJson('/api/reports/finance/expenses')->assertForbidden();
        $this->getJson('/api/reports/finance/profit-loss')->assertForbidden();
    }
}
