<?php

namespace Tests\Unit\Services;

use App\Models\GoodsReceiptNote;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchasingWorkflowService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchasingWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PurchasingWorkflowService $service;

    protected Product $product;

    protected Supplier $supplier;

    protected Warehouse $warehouse;

    protected StorageLocation $location;

    protected User $admin;

    protected PurchaseOrder $po;

    protected function setUp(): void
    {
        parent::setUp();

        Model::unguard();

        $this->service = app(PurchasingWorkflowService::class);

        $this->admin = User::forceCreate([
            'name' => 'Admin Test',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $this->product = Product::forceCreate([
            'sku' => 'RM-0001',
            'name' => 'Bahan Baku Test',
            'cost_price' => 10000,
        ]);

        $this->supplier = Supplier::forceCreate([
            'code' => 'SUP-01',
            'name' => 'Supplier Test',
            'status' => 'active',
        ]);

        $this->warehouse = Warehouse::forceCreate([
            'code' => 'WH-01',
            'name' => 'Gudang RM',
        ]);

        $this->location = StorageLocation::forceCreate([
            'warehouse_id' => $this->warehouse->id,
            'code' => 'LOC-01',
            'name' => 'Lokasi RM',
        ]);

        $this->po = PurchaseOrder::forceCreate([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-001',
            'po_date' => date('Y-m-d'),
            'total' => 100000,
            'status' => 'ordered',
        ]);

        $this->poItem = PurchaseOrderItem::forceCreate([
            'purchase_order_id' => $this->po->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 10000,
            'subtotal' => 100000,
            'received_qty' => 0,
        ]);
    }

    public function test_process_goods_receipt_increases_stock_updates_po_and_creates_payable()
    {
        $attributes = [
            'purchase_order_id' => $this->po->id,
            'warehouse_id' => $this->warehouse->id,
            'to_location_id' => $this->location->id,
            'received_by' => $this->admin->id,
            'receipt_date' => date('Y-m-d'),
            'delivery_order_number' => 'SJ-12345',
            'items' => [
                [
                    'purchase_order_item_id' => $this->poItem->id,
                    'product_id' => $this->product->id,
                    'received_qty' => 10,
                ],
            ],
        ];

        $grn = $this->service->processGoodsReceipt($attributes);

        $this->assertInstanceOf(GoodsReceiptNote::class, $grn);

        // Check stock
        $stock = DB::table('product_stocks')
            ->where('product_id', $this->product->id)
            ->where('location_id', $this->location->id)
            ->first();
        $this->assertEquals(10, $stock->quantity);

        // Check stock movement
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'to_location_id' => $this->location->id,
            'type' => 'in',
            'quantity' => 10,
            'reference_type' => 'goods_receipt',
            'reference_id' => $grn->id,
        ]);

        // Check PO status
        $this->po->refresh();
        $this->assertEquals('fully_received', $this->po->status);

        $this->poItem->refresh();
        $this->assertEquals(10, $this->poItem->received_qty);

        // Check Supplier Payable
        $this->assertDatabaseHas('supplier_payables', [
            'purchase_order_id' => $this->po->id,
            'amount' => 100000,
            'status' => 'open',
        ]);
    }
}
