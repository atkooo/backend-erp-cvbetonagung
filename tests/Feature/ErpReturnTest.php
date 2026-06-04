<?php

namespace Tests\Feature;

use App\Models\ProductReturn;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_return_seed_creates_customer_and_supplier_returns(): void
    {
        $this->seed();

        $this->assertDatabaseHas('returns', ['return_number' => 'RET-CUST-INIT']);
        $this->assertDatabaseHas('returns', ['return_number' => 'RET-SUP-INIT']);
        $this->assertDatabaseHas('return_items', ['quantity' => 1]);
    }

    public function test_customer_return_relations_are_available(): void
    {
        $this->seed();

        $productReturn = ProductReturn::query()
            ->where('return_number', 'RET-CUST-INIT')
            ->firstOrFail();

        $this->assertSame('customer', $productReturn->type);
        $this->assertSame('CUST-UMUM', $productReturn->customer?->code);
        $this->assertSame('SO-INIT', $productReturn->salesOrder?->order_number);
        $this->assertSame('admin@example.com', $productReturn->createdBy?->email);
        $this->assertSame('PRC-0001', $productReturn->items->first()?->product?->sku);
    }

    public function test_supplier_return_relations_are_available(): void
    {
        $this->seed();

        $productReturn = ProductReturn::query()
            ->where('return_number', 'RET-SUP-INIT')
            ->firstOrFail();

        $this->assertSame('supplier', $productReturn->type);
        $this->assertSame('SUP-UMUM', $productReturn->supplier?->code);
        $this->assertSame('PO-INIT', $productReturn->purchaseOrder?->po_number);
        $this->assertSame('MTL-0001', $productReturn->items->first()?->product?->sku);
        $this->assertSame('supplier_claim', $productReturn->qc_status);
    }

    public function test_parent_documents_can_reach_returns(): void
    {
        $this->seed();

        $salesOrder = SalesOrder::query()->where('order_number', 'SO-INIT')->firstOrFail();
        $purchaseOrder = PurchaseOrder::query()->where('po_number', 'PO-INIT')->firstOrFail();
        $supplier = Supplier::query()->where('code', 'SUP-UMUM')->firstOrFail();

        $this->assertTrue($salesOrder->productReturns->contains('return_number', 'RET-CUST-INIT'));
        $this->assertTrue($purchaseOrder->productReturns->contains('return_number', 'RET-SUP-INIT'));
        $this->assertTrue($supplier->productReturns->contains('return_number', 'RET-SUP-INIT'));
    }
}
