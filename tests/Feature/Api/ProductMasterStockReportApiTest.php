<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStock;
use App\Models\Role;
use App\Models\StorageLocation;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductMasterStockReportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_fetch_product_master_stock_report(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin, 'sanctum');

        $category = ProductCategory::query()->create(['name' => 'Beton Ready Mix']);
        $unit = Unit::query()->create(['code' => 'M3', 'name' => 'Meter Kubik']);
        $location = StorageLocation::query()->where('code', 'DEFAULT')->firstOrFail();

        $product = Product::query()->create([
            'sku' => 'PRD-BTR-001',
            'name' => 'Beton K-300',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'cost_price' => 800000,
            'selling_price' => 1100000,
            'min_stock' => 10,
            'qr_value' => 'QR-PRD-BTR-001',
            'status' => 'active',
        ]);

        ProductStock::query()->create([
            'product_id' => $product->id,
            'location_id' => $location->id,
            'quantity' => 25,
        ]);

        $response = $this->getJson('/api/reports/product-master-stock');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'total_products',
                        'total_stock_qty',
                        'total_cogs_value',
                        'total_selling_value',
                        'total_potential_profit',
                        'low_stock_count',
                        'out_of_stock_count',
                    ],
                    'rows' => [
                        '*' => [
                            'id',
                            'sku',
                            'name',
                            'category_name',
                            'unit_name',
                            'cost_price',
                            'selling_price',
                            'margin_amount',
                            'margin_percentage',
                            'min_stock',
                            'total_stock',
                            'stock_value_cogs',
                            'stock_value_selling',
                            'potential_profit',
                            'stock_status',
                            'qr_value',
                        ],
                    ],
                ],
            ]);

        $rows = collect($response->json('data.rows'));
        $target = $rows->firstWhere('sku', 'PRD-BTR-001');

        $this->assertNotNull($target);
        $this->assertEquals(25, $target['total_stock']);
        $this->assertEquals(800000, $target['cost_price']);
        $this->assertEquals(1100000, $target['selling_price']);
        $this->assertEquals(300000, $target['margin_amount']);
        $this->assertEquals(27.27, $target['margin_percentage']);
        $this->assertEquals(20000000, $target['stock_value_cogs']);
        $this->assertEquals(27500000, $target['stock_value_selling']);
        $this->assertEquals(7500000, $target['potential_profit']);
        $this->assertEquals('aman', $target['stock_status']);
        $this->assertEquals('QR-PRD-BTR-001', $target['qr_value']);
    }

    public function test_product_master_stock_report_filters_by_category_and_search(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin, 'sanctum');

        $categoryA = ProductCategory::query()->create(['name' => 'Kategori Alfa']);
        $categoryB = ProductCategory::query()->create(['name' => 'Kategori Beta']);

        Product::query()->create([
            'sku' => 'SKU-ALFA-1',
            'name' => 'Produk Super Alfa',
            'category_id' => $categoryA->id,
            'cost_price' => 100,
            'selling_price' => 200,
            'min_stock' => 5,
        ]);

        Product::query()->create([
            'sku' => 'SKU-BETA-2',
            'name' => 'Produk Mega Beta',
            'category_id' => $categoryB->id,
            'cost_price' => 100,
            'selling_price' => 200,
            'min_stock' => 5,
        ]);

        $resCat = $this->getJson('/api/reports/product-master-stock?category_id='.$categoryA->id);
        $resCat->assertOk();
        $skus = collect($resCat->json('data.rows'))->pluck('sku');
        $this->assertContains('SKU-ALFA-1', $skus);
        $this->assertNotContains('SKU-BETA-2', $skus);

        $resSearch = $this->getJson('/api/reports/product-master-stock?search=Mega');
        $resSearch->assertOk();
        $skusSearch = collect($resSearch->json('data.rows'))->pluck('sku');
        $this->assertContains('SKU-BETA-2', $skusSearch);
        $this->assertNotContains('SKU-ALFA-1', $skusSearch);
    }

    public function test_product_master_stock_report_validation_fails_on_invalid_category_id(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin, 'sanctum');

        $response = $this->getJson('/api/reports/product-master-stock?category_id=invalid-uuid-1234');
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $this->seed();

        $role = Role::query()->create(['code' => 'no_reports_role', 'name' => 'No Reports Role']);
        $user = User::query()->create([
            'name' => 'Restricted User',
            'email' => 'restricted@example.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/reports/product-master-stock');
        $response->assertForbidden();
    }
}
