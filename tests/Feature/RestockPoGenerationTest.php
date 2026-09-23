<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestockPoGenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Supplier $supplier;

    private Unit $unit;

    private StorageLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->supplier = Supplier::create([
            'name' => 'PT Semen Perkasa',
            'phone' => '082199887766',
        ]);
        $this->unit = Unit::create([
            'name' => 'Zak',
            'code' => 'ZAK',
        ]);

        $warehouse = Warehouse::create([
            'name' => 'Gudang Utama',
            'code' => 'G-UTM',
        ]);

        $this->location = StorageLocation::create([
            'warehouse_id' => $warehouse->id,
            'name' => 'Rak A-1',
            'code' => 'A-1',
        ]);
    }

    public function test_can_get_restock_suggestions_for_empty_and_low_stock_products(): void
    {
        // 1. Produk dengan stok 0 (Habis)
        $emptyProduct = Product::create([
            'sku' => 'PRD-SEMEN-01',
            'name' => 'Semen Padang 50kg',
            'type' => 'raw_material',
            'unit_id' => $this->unit->id,
            'cost_price' => 65000,
            'selling_price' => 75000,
            'min_stock' => 100,
        ]);

        // 2. Produk dengan stok menipis (Stok 10 <= Min 50)
        $lowProduct = Product::create([
            'sku' => 'PRD-PASIR-01',
            'name' => 'Pasir Pasang Super',
            'type' => 'raw_material',
            'unit_id' => $this->unit->id,
            'cost_price' => 200000,
            'selling_price' => 250000,
            'min_stock' => 50,
        ]);
        ProductStock::create([
            'product_id' => $lowProduct->id,
            'location_id' => $this->location->id,
            'quantity' => 10,
        ]);

        // 3. Produk dengan stok aman (Stok 150 > Min 50)
        $safeProduct = Product::create([
            'sku' => 'PRD-SPLIT-01',
            'name' => 'Batu Split 1-2',
            'type' => 'raw_material',
            'unit_id' => $this->unit->id,
            'cost_price' => 180000,
            'selling_price' => 220000,
            'min_stock' => 50,
        ]);
        ProductStock::create([
            'product_id' => $safeProduct->id,
            'location_id' => $this->location->id,
            'quantity' => 150,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/purchasing/restock-suggestions');

        $response->assertOk();
        $rows = $response->json('data.rows');

        // Pastikan hanya 2 produk yang masuk saran restok (kosong dan menipis)
        $this->assertCount(2, $rows);

        $skuList = array_column($rows, 'sku');
        $this->assertContains('PRD-SEMEN-01', $skuList);
        $this->assertContains('PRD-PASIR-01', $skuList);
        $this->assertNotContains('PRD-SPLIT-01', $skuList);

        // Cek defisit dan suggested qty
        $semenRow = collect($rows)->firstWhere('sku', 'PRD-SEMEN-01');
        $this->assertEquals(100, $semenRow['deficit_qty']);
        $this->assertEquals(100, $semenRow['suggested_order_qty']);
        $this->assertEquals('empty', $semenRow['stock_status']);

        $pasirRow = collect($rows)->firstWhere('sku', 'PRD-PASIR-01');
        $this->assertEquals(40, $pasirRow['deficit_qty']);
        $this->assertEquals(40, $pasirRow['suggested_order_qty']);
        $this->assertEquals('low', $pasirRow['stock_status']);
    }

    public function test_can_generate_purchase_order_from_restock_items(): void
    {
        $product = Product::create([
            'sku' => 'PRD-BESI-01',
            'name' => 'Besi Ulir 12mm',
            'type' => 'raw_material',
            'unit_id' => $this->unit->id,
            'cost_price' => 110000,
            'selling_price' => 135000,
            'min_stock' => 200,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/purchasing/restock-generate-po', [
            'supplier_id' => $this->supplier->id,
            'po_date' => date('Y-m-d'),
            'notes' => 'PO Restok Otomatis Bulanan',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 200,
                    'unit_price' => 110000,
                    'description' => 'Restok Besi Ulir 12mm',
                ],
            ],
        ]);

        $response->assertStatus(201);
        $poId = $response->json('data.id');

        $this->assertNotNull($poId);
        $po = PurchaseOrder::with('items')->find($poId);

        $this->assertNotNull($po);
        $this->assertEquals($this->supplier->id, $po->supplier_id);
        $this->assertEquals(22000000, (float) $po->total);
        $this->assertEquals('draft', $po->status);
        $this->assertCount(1, $po->items);
        $this->assertEquals(200, (float) $po->items[0]->quantity);
        $this->assertEquals(110000, (float) $po->items[0]->unit_price);
    }

    public function test_generate_restock_po_fails_with_invalid_supplier_or_empty_items(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/purchasing/restock-generate-po', [
            'supplier_id' => 'non-existent-uuid',
            'items' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['supplier_id', 'items']);
    }
}
