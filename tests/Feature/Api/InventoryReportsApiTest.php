<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStock;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReportsApiTest extends TestCase
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
            'name' => 'Admin Test',
            'email' => 'admintest@example.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }

    public function test_user_can_fetch_stock_mutation_report(): void
    {
        $category = ProductCategory::query()->create(['name' => 'Semen & Pasir']);
        $unit = Unit::query()->create(['code' => 'ZAK', 'name' => 'Zak 50kg']);
        $warehouse = Warehouse::query()->create(['code' => 'WH-MAIN', 'name' => 'Gudang Utama']);
        $location = StorageLocation::query()->create(['warehouse_id' => $warehouse->id, 'code' => 'LOC-A1', 'name' => 'Rak A1']);

        $product = Product::query()->create([
            'sku' => 'MAT-SMN-001',
            'name' => 'Semen Tiga Roda 50kg',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'cost_price' => 65000,
            'selling_price' => 75000,
            'min_stock' => 50,
            'status' => 'active',
        ]);

        StockMovement::query()->create([
            'product_id' => $product->id,
            'to_location_id' => $location->id,
            'type' => 'in',
            'quantity' => 100,
            'reference_number' => 'GRN-202607-001',
            'reference_type' => 'goods_receipt',
            'handled_by' => $this->adminUser->id,
            'movement_at' => now(),
            'notes' => 'Penerimaan bahan baku semen',
        ]);

        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->getJson('/api/reports/inventory/mutation?search=MAT-SMN');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'total_movements',
                        'total_qty_in',
                        'total_qty_out',
                        'total_qty_transfer',
                        'total_qty_adjustment',
                    ],
                    'rows' => [
                        '*' => [
                            'id',
                            'movement_at',
                            'type',
                            'sku',
                            'product_name',
                            'quantity',
                        ],
                    ],
                ],
            ]);
    }

    public function test_user_can_fetch_low_stock_report(): void
    {
        $category = ProductCategory::query()->create(['name' => 'Batu Spilit']);
        $unit = Unit::query()->create(['code' => 'M3', 'name' => 'Kubik']);

        $product = Product::query()->create([
            'sku' => 'MAT-SPL-001',
            'name' => 'Batu Spilit 1x2',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'cost_price' => 200000,
            'selling_price' => 250000,
            'min_stock' => 100,
            'status' => 'active',
        ]);

        $warehouse = Warehouse::query()->create(['code' => 'WH-SPILIT', 'name' => 'Gudang Spilit']);
        $location = StorageLocation::query()->create(['warehouse_id' => $warehouse->id, 'code' => 'LOC-B1', 'name' => 'Area B1']);

        ProductStock::query()->create([
            'product_id' => $product->id,
            'location_id' => $location->id,
            'quantity' => 15, // Total 15 <= Min 100 (Stok Menipis)
        ]);

        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->getJson('/api/reports/inventory/low-stock');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'total_low_stock_items',
                        'low_stock_count',
                        'out_of_stock_count',
                        'total_estimated_reorder_cost',
                    ],
                    'rows',
                ],
            ]);
    }

    public function test_user_can_fetch_inventory_valuation_report(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->getJson('/api/reports/inventory/valuation');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'total_warehouses',
                        'total_categories',
                        'grand_total_qty',
                        'grand_total_cogs_value',
                        'grand_total_selling_value',
                        'grand_potential_profit',
                    ],
                    'by_warehouse',
                    'by_category',
                ],
            ]);
    }

    public function test_user_can_fetch_dead_stock_report(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->getJson('/api/reports/inventory/dead-stock?days=30');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'total_dead_stock_items',
                        'total_idle_qty',
                        'total_tied_cogs_value',
                        'total_tied_selling_value',
                        'threshold_days',
                    ],
                    'rows',
                ],
            ]);
    }

    public function test_unauthorized_user_cannot_access_inventory_reports(): void
    {
        $noPermRole = Role::query()->create(['code' => 'no_perm', 'name' => 'No Perm']);
        $restrictedUser = User::query()->create([
            'name' => 'Restricted User',
            'email' => 'restricted_inv@example.com',
            'password' => bcrypt('password'),
            'role_id' => $noPermRole->id,
            'status' => 'active',
        ]);

        $this->actingAs($restrictedUser, 'sanctum');

        $this->getJson('/api/reports/inventory/mutation')->assertForbidden();
        $this->getJson('/api/reports/inventory/low-stock')->assertForbidden();
        $this->getJson('/api/reports/inventory/valuation')->assertForbidden();
        $this->getJson('/api/reports/inventory/dead-stock')->assertForbidden();
    }
}
