<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_order_item_and_payable_can_be_created(): void
    {
        $this->seed();

        $supplier = Supplier::query()->where('code', 'SUP-UMUM')->firstOrFail();
        $product = Product::query()->where('sku', 'MTL-0001')->firstOrFail();

        $poResponse = $this->postJson('/api/purchasing/purchase-orders', [
            'po_number' => 'PO-API-001',
            'supplier_id' => $supplier->id,
            'po_date' => '2026-06-05',
            'total' => 750000,
            'status' => 'ordered',
            'notes' => 'API purchase order.',
        ]);

        $poResponse
            ->assertCreated()
            ->assertJsonPath('data.po_number', 'PO-API-001')
            ->assertJsonPath('data.supplier.code', 'SUP-UMUM');

        $poId = $poResponse->json('data.id');

        $this->postJson('/api/purchasing/purchase-order-items', [
            'purchase_order_id' => $poId,
            'product_id' => $product->id,
            'description' => 'API material item',
            'quantity' => 3,
            'unit_price' => 250000,
            'received_qty' => 1,
            'subtotal' => 750000,
        ])
            ->assertCreated()
            ->assertJsonPath('data.purchase_order.po_number', 'PO-API-001')
            ->assertJsonPath('data.product.sku', 'MTL-0001');

        $this->postJson('/api/purchasing/supplier-payables', [
            'purchase_order_id' => $poId,
            'supplier_id' => $supplier->id,
            'payable_number' => 'PAYABLE-API-001',
            'due_date' => '2026-06-20',
            'amount' => 750000,
            'paid_amount' => 0,
            'status' => 'open',
        ])
            ->assertCreated()
            ->assertJsonPath('data.payable_number', 'PAYABLE-API-001')
            ->assertJsonPath('data.purchase_order.po_number', 'PO-API-001');

        $this->getJson('/api/purchasing/purchase-orders?q=PO-API')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_supplier_return_and_item_can_be_created(): void
    {
        $this->seed();

        $supplier = Supplier::query()->where('code', 'SUP-UMUM')->firstOrFail();
        $product = Product::query()->where('sku', 'MTL-0001')->firstOrFail();
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $poId = $this->postJson('/api/purchasing/purchase-orders', [
            'po_number' => 'PO-API-RET',
            'supplier_id' => $supplier->id,
            'po_date' => '2026-06-05',
            'total' => 250000,
            'status' => 'ordered',
        ])->assertCreated()->json('data.id');

        $returnResponse = $this->postJson('/api/purchasing/returns', [
            'return_number' => 'RET-API-SUP-001',
            'type' => 'supplier',
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $poId,
            'reason' => 'Material did not pass QC.',
            'qc_status' => 'supplier_claim',
            'created_by' => $admin->id,
        ]);

        $returnResponse
            ->assertCreated()
            ->assertJsonPath('data.return_number', 'RET-API-SUP-001')
            ->assertJsonPath('data.supplier.code', 'SUP-UMUM')
            ->assertJsonPath('data.purchase_order.po_number', 'PO-API-RET');

        $returnId = $returnResponse->json('data.id');

        $this->postJson('/api/purchasing/return-items', [
            'return_id' => $returnId,
            'product_id' => $product->id,
            'quantity' => 1,
            'notes' => 'Returned via API.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.product_return.return_number', 'RET-API-SUP-001')
            ->assertJsonPath('data.product.sku', 'MTL-0001');

        $this->getJson('/api/purchasing/returns?type=supplier&q=RET-API')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_purchase_order_can_be_received_into_stock_movements(): void
    {
        $this->seed();

        $supplier = Supplier::query()->where('code', 'SUP-UMUM')->firstOrFail();
        $product = Product::query()->where('sku', 'MTL-0001')->firstOrFail();
        $location = StorageLocation::query()->where('code', 'DEFAULT')->firstOrFail();
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        ProductStock::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'location_id' => $location->id,
            ],
            ['quantity' => 0],
        );

        $poId = $this->postJson('/api/purchasing/purchase-orders', [
            'po_number' => 'PO-API-RECEIVE',
            'supplier_id' => $supplier->id,
            'po_date' => '2026-06-05',
            'total' => 600000,
            'status' => 'ordered',
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/purchasing/purchase-order-items', [
            'purchase_order_id' => $poId,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 200000,
            'received_qty' => 0,
            'subtotal' => 600000,
        ])->assertCreated();

        $this->postJson("/api/purchasing/purchase-orders/{$poId}/receive", [
            'to_location_id' => $location->id,
            'handled_by' => $admin->id,
            'movement_at' => '2026-06-06 08:00:00',
            'notes' => 'Received via API workflow.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'fully_received')
            ->assertJsonPath('data.items.0.received_qty', '3.00');

        $stock = ProductStock::query()
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->firstOrFail();

        $this->assertSame('3.00', $stock->quantity);

        $movement = StockMovement::query()
            ->where('reference_number', 'PO-API-RECEIVE')
            ->firstOrFail();

        $this->assertSame('in', $movement->type);
        $this->assertSame('3.00', $movement->quantity);
        $this->assertSame($admin->id, $movement->handled_by);
    }

    public function test_purchasing_api_rejects_invalid_purchase_order_status(): void
    {
        $this->seed();

        $supplier = Supplier::query()->where('code', 'SUP-UMUM')->firstOrFail();

        $this->postJson('/api/purchasing/purchase-orders', [
            'po_number' => 'PO-API-BAD',
            'supplier_id' => $supplier->id,
            'po_date' => '2026-06-05',
            'status' => 'processing',
        ])->assertUnprocessable();
    }
}
