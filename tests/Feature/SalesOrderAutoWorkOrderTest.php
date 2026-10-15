<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductionWorkOrder;
use App\Models\SalesOrder;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderAutoWorkOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->user = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->customer = Customer::firstOrCreate(
            ['name' => 'PT Properti Sentosa'],
            ['phone' => '08123456789']
        );
        $this->unit = Unit::firstOrCreate(
            ['code' => 'PCS'],
            ['name' => 'Pieces']
        );
    }

    public function test_sales_order_in_draft_cannot_generate_work_orders_until_approved(): void
    {
        // 1. Buat produk jadi
        $finishedGood = Product::create([
            'sku' => 'PRD-FG-001',
            'name' => 'Pagar Panel Beton 240x40x5',
            'type' => 'finished_good',
            'unit_id' => $this->unit->id,
            'cost_price' => 75000,
            'selling_price' => 125000,
            'min_stock' => 50,
        ]);

        $rawMaterial = Product::create([
            'sku' => 'PRD-RM-001',
            'name' => 'Semen Portland 50kg',
            'type' => 'raw_material',
            'unit_id' => $this->unit->id,
            'cost_price' => 60000,
            'selling_price' => 70000,
            'min_stock' => 100,
        ]);

        // 2. Buat Sales Order (status awal draft)
        $response = $this->actingAs($this->user)->postJson('/api/sales/sales-orders', [
            'customer_id' => $this->customer->id,
            'order_date' => date('Y-m-d'),
            'notes' => 'Pesanan Pagar Panel dan Semen',
            'items' => [
                [
                    'product_id' => $finishedGood->id,
                    'quantity' => 150,
                    'unit_price' => 125000,
                ],
                [
                    'product_id' => $rawMaterial->id,
                    'quantity' => 20,
                    'unit_price' => 70000,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $soId = $response->json('data.id');

        // 3. Saat masih Draft, Work Order BELUM boleh dibuat
        $this->assertEquals(0, ProductionWorkOrder::where('sales_order_id', $soId)->count());

        // 4. Trigger manual saat draft harus ditolak dengan status 422
        $manualRes = $this->actingAs($this->user)->postJson("/api/sales/sales-orders/{$soId}/generate-work-orders");
        $manualRes->assertStatus(422);
        $this->assertStringContainsString('Draft', (string) $manualRes->json('message'));

        // 5. Setelah diapprove, barulah Work Order terbentuk
        $approveRes = $this->actingAs($this->user)->postJson("/api/sales/sales-orders/{$soId}/approve");
        $approveRes->assertOk();

        $wos = ProductionWorkOrder::where('sales_order_id', $soId)->get();
        $this->assertCount(1, $wos);

        $wo = $wos->first();
        $this->assertEquals($finishedGood->id, $wo->product_id);
        $this->assertEquals(150, (float) $wo->target_qty);
        $this->assertEquals('Draft', $wo->stage);
        $this->assertEquals(0, $wo->progress);
        $this->assertStringContainsString($this->customer->name, $wo->source_label);

        // 6. Pastikan 4 task produksi otomatis ter-generate dalam status 'Pending'
        $this->assertCount(4, $wo->tasks);
        foreach ($wo->tasks as $task) {
            $this->assertEquals('Pending', $task->status);
        }

        // 7. Pastikan produk bahan baku TIDAK dibuatkan Work Order
        $this->assertFalse(ProductionWorkOrder::where('sales_order_id', $soId)->where('product_id', $rawMaterial->id)->exists());
    }

    public function test_auto_generate_work_orders_is_idempotent(): void
    {
        $product = Product::create([
            'sku' => 'PRD-UD-001',
            'name' => 'U-Ditch 40x40',
            'type' => 'finished_good',
            'unit_id' => $this->unit->id,
            'cost_price' => 100000,
            'selling_price' => 150000,
            'min_stock' => 10,
        ]);

        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'order_date' => date('Y-m-d'),
            'status' => 'draft',
            'total' => 300000,
        ]);

        $so->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 150000,
            'subtotal' => 300000,
        ]);

        // Saat status draft, generate manual ditolak
        $draftRes = $this->actingAs($this->user)->postJson("/api/sales/sales-orders/{$so->id}/generate-work-orders");
        $draftRes->assertStatus(422);

        // Set status ke approved
        $so->update(['status' => 'approved']);

        // Trigger manual pertama kali
        $res1 = $this->actingAs($this->user)->postJson("/api/sales/sales-orders/{$so->id}/generate-work-orders");
        $res1->assertOk();
        $this->assertEquals(1, ProductionWorkOrder::where('sales_order_id', $so->id)->count());

        // Trigger manual kedua kali (tidak boleh generate lagi selama WO masih aktif)
        $res2 = $this->actingAs($this->user)->postJson("/api/sales/sales-orders/{$so->id}/generate-work-orders");
        $res2->assertStatus(422);
        $this->assertEquals(1, ProductionWorkOrder::where('sales_order_id', $so->id)->count());

        // Batalkan WO yang ada
        $existingWo = ProductionWorkOrder::where('sales_order_id', $so->id)->first();
        $existingWo->update(['stage' => 'cancelled']);

        // Sekarang karena semua WO dibatalkan, dapat digenerate ulang
        $res3 = $this->actingAs($this->user)->postJson("/api/sales/sales-orders/{$so->id}/generate-work-orders");
        $res3->assertOk();
        $this->assertEquals(2, ProductionWorkOrder::where('sales_order_id', $so->id)->count());
        $this->assertEquals(1, ProductionWorkOrder::where('sales_order_id', $so->id)->active()->count());
    }

    public function test_sales_order_with_multiple_items_same_product_different_sizes_creates_multiple_work_orders(): void
    {
        $product = Product::create([
            'sku' => 'PRD-TIANG-01',
            'name' => 'Tiang Serut Kotak 30 cm',
            'type' => 'finished_good',
            'unit_id' => $this->unit->id,
            'cost_price' => 350000,
            'selling_price' => 450000,
            'min_stock' => 5,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/sales/sales-orders', [
            'customer_id' => $this->customer->id,
            'order_date' => date('Y-m-d'),
            'notes' => 'Tiang beton custom ukuran',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 8.2,
                    'piece_count' => 2,
                    'length' => 4.10,
                    'unit_price' => 450000,
                ],
                [
                    'product_id' => $product->id,
                    'quantity' => 6.6,
                    'piece_count' => 2,
                    'length' => 3.30,
                    'unit_price' => 450000,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $soId = $response->json('data.id');

        // Belum di-approve, WO belum ada
        $this->assertEquals(0, ProductionWorkOrder::where('sales_order_id', $soId)->count());

        // Approve SO
        $approveRes = $this->actingAs($this->user)->postJson("/api/sales/sales-orders/{$soId}/approve");
        $approveRes->assertOk();

        $wos = ProductionWorkOrder::where('sales_order_id', $soId)->get();

        // Harus membuat 2 WO meskipun produknya sama, karena beda ukuran/item
        $this->assertCount(2, $wos);

        $wo1 = $wos->first(fn ($w) => str_contains((string) $w->source_label, '4.1m'));
        $wo2 = $wos->first(fn ($w) => str_contains((string) $w->source_label, '3.3m'));

        $this->assertNotNull($wo1);
        $this->assertNotNull($wo2);
        $this->assertEquals(2, (float) $wo1->target_qty);
        $this->assertEquals(2, (float) $wo2->target_qty);
        $this->assertNotEquals($wo1->id, $wo2->id);
    }

    public function test_sales_order_distinguishes_manufactured_vs_purchased_products(): void
    {
        // 1. Produk barang jadi yang diproduksi sendiri di pabrik (procurement_type: manufacture)
        $manufacturedProduct = Product::create([
            'sku' => 'PRD-MFG-001',
            'name' => 'Box Culvert 100x100',
            'type' => 'finished_good',
            'procurement_type' => 'manufacture',
            'unit_id' => $this->unit->id,
            'cost_price' => 500000,
            'selling_price' => 750000,
            'min_stock' => 5,
        ]);

        // 2. Produk barang jadi yang dibeli jadi dari supplier / trading (procurement_type: purchase)
        $purchasedProduct = Product::create([
            'sku' => 'PRD-PUR-001',
            'name' => 'Pipa PVC Wavin 4 inch',
            'type' => 'finished_good',
            'procurement_type' => 'purchase',
            'unit_id' => $this->unit->id,
            'cost_price' => 120000,
            'selling_price' => 160000,
            'min_stock' => 20,
        ]);

        // 3. Buat Sales Order via API
        $response = $this->actingAs($this->user)->postJson('/api/sales/sales-orders', [
            'customer_id' => $this->customer->id,
            'order_date' => date('Y-m-d'),
            'notes' => 'Pesanan Gabungan Fabrikasi dan Beli Jadi',
            'items' => [
                [
                    'product_id' => $manufacturedProduct->id,
                    'quantity' => 10,
                    'unit_price' => 750000,
                ],
                [
                    'product_id' => $purchasedProduct->id,
                    'quantity' => 50,
                    'unit_price' => 160000,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $soId = $response->json('data.id');

        // Belum di-approve -> WO belum ada
        $this->assertEquals(0, ProductionWorkOrder::where('sales_order_id', $soId)->count());

        // Approve SO
        $this->actingAs($this->user)->postJson("/api/sales/sales-orders/{$soId}/approve")->assertOk();

        // 4. Pastikan Work Order HANYA terbentuk untuk produk 'manufacture'
        $wos = ProductionWorkOrder::where('sales_order_id', $soId)->get();

        $this->assertCount(1, $wos);
        $wo = $wos->first();
        $this->assertEquals($manufacturedProduct->id, $wo->product_id);
        $this->assertEquals(10, (float) $wo->target_qty);

        // 5. Pastikan produk 'purchase' TIDAK dibuatkan Work Order
        $this->assertFalse(ProductionWorkOrder::where('sales_order_id', $soId)->where('product_id', $purchasedProduct->id)->exists());
    }
}
