<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierPayable;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasingReportsApiTest extends TestCase
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
            'email' => 'adminpurtest@example.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }

    public function test_user_can_fetch_supplier_purchases_report(): void
    {
        $supplier = Supplier::query()->create([
            'code' => 'SPL-001',
            'name' => 'PT Semen Indonesia',
            'contact_name' => 'Budi Santoso',
            'phone' => '081234567890',
            'city' => 'Gresik',
        ]);

        $po = PurchaseOrder::query()->create([
            'po_number' => 'PO-202607-001',
            'supplier_id' => $supplier->id,
            'po_date' => now()->format('Y-m-d'),
            'total' => 50000000,
            'status' => 'approved',
        ]);

        SupplierPayable::query()->create([
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'payable_number' => 'AP-202607-001',
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'amount' => 50000000,
            'paid_amount' => 20000000,
            'status' => 'partial',
        ]);

        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->getJson('/api/reports/purchasing/supplier?search=PT Semen');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'total_suppliers',
                        'total_po_count',
                        'total_purchase_amount',
                        'total_paid_amount',
                        'total_outstanding_ap',
                    ],
                    'rows' => [
                        '*' => [
                            'supplier_id',
                            'supplier_code',
                            'supplier_name',
                            'po_count',
                            'total_purchase_amount',
                            'total_paid_amount',
                            'total_outstanding_ap',
                        ],
                    ],
                ],
            ]);
    }

    public function test_user_can_fetch_ap_aging_report(): void
    {
        $supplier = Supplier::query()->create([
            'code' => 'SPL-002',
            'name' => 'CV Pasir Jaya',
        ]);

        $po = PurchaseOrder::query()->create([
            'po_number' => 'PO-202607-002',
            'supplier_id' => $supplier->id,
            'po_date' => now()->subDays(45)->format('Y-m-d'),
            'total' => 15000000,
            'status' => 'approved',
        ]);

        SupplierPayable::query()->create([
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'payable_number' => 'AP-202607-002',
            'due_date' => now()->subDays(15)->format('Y-m-d'), // Overdue 15 days
            'amount' => 15000000,
            'paid_amount' => 0,
            'status' => 'open',
        ]);

        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->getJson('/api/reports/purchasing/ap-aging');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'as_of_date',
                    'buckets' => [
                        'current',
                        '1_30',
                        '31_60',
                        '61_90',
                        'over_90',
                    ],
                    'summary' => [
                        'total_open_payables',
                        'total_ap_amount',
                        'total_paid_amount',
                        'total_outstanding_ap',
                    ],
                    'payables',
                ],
            ]);
    }

    public function test_user_can_fetch_purchase_price_analysis_report(): void
    {
        $category = ProductCategory::query()->create(['name' => 'Pasir']);
        $unit = Unit::query()->create(['code' => 'M3', 'name' => 'Kubik']);
        $supplier = Supplier::query()->create(['code' => 'SPL-003', 'name' => 'TB Pasir Abadi']);

        $product = Product::query()->create([
            'sku' => 'MAT-PSR-001',
            'name' => 'Pasir Cor Super',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'cost_price' => 180000,
            'selling_price' => 220000,
            'min_stock' => 50,
            'status' => 'active',
        ]);

        $po = PurchaseOrder::query()->create([
            'po_number' => 'PO-202607-003',
            'supplier_id' => $supplier->id,
            'po_date' => now()->format('Y-m-d'),
            'total' => 9000000,
            'status' => 'approved',
        ]);

        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 50,
            'unit_price' => 180000,
            'subtotal' => 9000000,
        ]);

        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->getJson('/api/reports/purchasing/price-analysis?search=Pasir');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'total_analyzed_products',
                        'total_po_items',
                    ],
                    'rows' => [
                        '*' => [
                            'id',
                            'sku',
                            'name',
                            'master_cost_price',
                            'latest_purchase_price',
                            'min_purchase_price',
                            'max_purchase_price',
                            'avg_purchase_price',
                            'price_variance_pct',
                            'price_trend',
                        ],
                    ],
                ],
            ]);
    }

    public function test_unauthorized_user_cannot_access_purchasing_reports(): void
    {
        $noPermRole = Role::query()->create(['code' => 'no_perm', 'name' => 'No Perm']);
        $restrictedUser = User::query()->create([
            'name' => 'Restricted User',
            'email' => 'restricted_pur@example.com',
            'password' => bcrypt('password'),
            'role_id' => $noPermRole->id,
            'status' => 'active',
        ]);

        $this->actingAs($restrictedUser, 'sanctum');

        $this->getJson('/api/reports/purchasing/supplier')->assertForbidden();
        $this->getJson('/api/reports/purchasing/ap-aging')->assertForbidden();
        $this->getJson('/api/reports/purchasing/price-analysis')->assertForbidden();
    }
}
