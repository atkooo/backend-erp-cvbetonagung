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

        $this->user = User::factory()->create();
        $this->customer = Customer::create([
            'name' => 'PT Properti Sentosa',
            'phone' => '08123456789',
        ]);
        $this->unit = Unit::create([
            'name' => 'Pieces',
            'code' => 'PCS',
        ]);
    }

    public function test_sales_order_auto_creates_work_order_for_self_produced_product(): void
    {
        // 1. Buat produk jadi (barang produksi sendiri)
        $finishedGood = Product::create([
            'sku' => 'PRD-FG-001',
            'name' => 'Pagar Panel Beton 240x40x5',
            'type' => 'finished_good',
            'unit_id' => $this->unit->id,
            'cost_price' => 75000,
            'selling_price' => 125000,
            'min_stock' => 50,
        ]);

        // 2. Buat produk bahan baku (bukan produksi sendiri / beli ke supplier)
        $rawMaterial = Product::create([
            'sku' => 'PRD-RM-001',
            'name' => 'Semen Portland 50kg',
            'type' => 'raw_material',
            'unit_id' => $this->unit->id,
            'cost_price' => 60000,
            'selling_price' => 70000,
            'min_stock' => 100,
        ]);

        // 3. Buat Sales Order via API
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

        // 4. Periksa bahwa Work Order otomatis terbentuk HANYA untuk barang produksi sendiri
        $wos = ProductionWorkOrder::where('sales_order_id', $soId)->get();

        $this->assertCount(1, $wos);
        $wo = $wos->first();

        $this->assertEquals($finishedGood->id, $wo->product_id);
        $this->assertEquals(150, (float) $wo->target_qty);
        $this->assertEquals('Draft', $wo->stage);
        $this->assertEquals(0, $wo->progress);
        $this->assertStringContainsString($this->customer->name, $wo->source_label);

        // 5. Periksa bahwa 4 task produksi otomatis ter-generate dalam status 'Pending'
        $this->assertCount(4, $wo->tasks);
        foreach ($wo->tasks as $task) {
            $this->assertEquals('Pending', $task->status);
        }

        // 6. Pastikan produk bahan mentah TIDAK dibuatkan Work Order
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

        // Trigger manual pertama kali
        $res1 = $this->actingAs($this->user)->postJson("/api/sales/sales-orders/{$so->id}/generate-work-orders");
        $res1->assertOk();
        $this->assertEquals(1, ProductionWorkOrder::where('sales_order_id', $so->id)->count());

        // Trigger manual kedua kali (tidak boleh duplikasi)
        $res2 = $this->actingAs($this->user)->postJson("/api/sales/sales-orders/{$so->id}/generate-work-orders");
        $res2->assertOk();
        $this->assertEquals(1, ProductionWorkOrder::where('sales_order_id', $so->id)->count());
    }
}
