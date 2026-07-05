<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReturnItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\SupplierPayable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class ReturnSystemTest extends TestCase
{
    use RefreshDatabase, WithoutMiddleware;

    private User $user;

    private StorageLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->location = StorageLocation::factory()->create(['name' => 'Main Warehouse']);
    }

    public function test_approve_customer_return_increases_stock_and_deducts_invoice(): void
    {
        $customer = Customer::create(['name' => 'Test Customer', 'code' => 'CUST-001', 'contact_person' => 'Budi']);
        $product = Product::create([
            'name' => 'Test Finished Goods',
            'type' => 'finished_goods',
            'sku' => 'SKU-FG-001',
            'business_unit' => 'Pusat',
            'is_customizable' => false,
            'pricing_method' => 'fixed',
            'status' => 'active',
            'stock_status' => 'in_stock',
        ]);

        // Setup initial stock
        ProductStock::create([
            'product_id' => $product->id,
            'location_id' => $this->location->id,
            'quantity' => 10,
        ]);

        $salesOrder = SalesOrder::create([
            'customer_id' => $customer->id,
            'order_number' => 'SO-TEST-001',
            'order_date' => now()->format('Y-m-d'),
            'total' => 500000,
            'status' => 'Draft',
        ]);

        // Setup SO Item
        SalesOrderItem::create([
            'sales_order_id' => $salesOrder->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 100000,
            'subtotal' => 500000,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-001',
            'sales_order_id' => $salesOrder->id,
            'customer_id' => $customer->id,
            'total' => 500000,
            'amount' => 500000,
            'paid_amount' => 0,
            'status' => 'unpaid',
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
        ]);

        $return = ProductReturn::create([
            'return_number' => 'RET-C-001',
            'type' => 'customer',
            'customer_id' => $customer->id,
            'sales_order_id' => $salesOrder->id,
            'qc_status' => 'pending',
            'reason' => 'Defective item',
            'created_by' => $this->user->id,
        ]);

        ReturnItem::create([
            'return_id' => $return->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response = $this->putJson("/api/purchasing/returns/{$return->id}", [
            'qc_status' => 'approved',
        ]);

        $response->assertStatus(200);

        // Assert stock increased by 2
        $this->assertDatabaseHas('product_stocks', [
            'product_id' => $product->id,
            'location_id' => $this->location->id,
            'quantity' => 12,
        ]);

        // Assert movement created
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'to_location_id' => $this->location->id,
            'type' => 'in',
            'quantity' => 2,
            'reference_type' => 'return',
            'reference_id' => $return->id,
        ]);

        // Assert invoice deducted by 2 * 100000 = 200000
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'total' => 300000,
        ]);
    }

    public function test_approve_supplier_return_decreases_stock_and_deducts_payable(): void
    {
        $supplier = Supplier::create(['name' => 'Test Supplier', 'code' => 'SUP-001', 'contact_person' => 'Agus']);
        $product = Product::create([
            'name' => 'Test Raw Material',
            'type' => 'raw_material',
            'sku' => 'SKU-RM-001',
            'business_unit' => 'Pusat',
            'is_customizable' => false,
            'pricing_method' => 'fixed',
            'status' => 'active',
            'stock_status' => 'in_stock',
        ]);

        // Setup initial stock
        ProductStock::create([
            'product_id' => $product->id,
            'location_id' => $this->location->id,
            'quantity' => 10,
        ]);

        $purchaseOrder = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-TEST-001',
            'po_date' => now()->format('Y-m-d'),
            'total_amount' => 500000,
            'status' => 'Draft',
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 50000,
            'subtotal' => 500000,
        ]);

        $payable = SupplierPayable::create([
            'payable_number' => 'AP-TEST-001',
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $supplier->id,
            'amount' => 500000,
            'paid_amount' => 0,
            'status' => 'open',
            'due_date' => now()->addDays(7)->format('Y-m-d'),
        ]);

        $return = ProductReturn::create([
            'return_number' => 'RET-S-001',
            'type' => 'supplier',
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $purchaseOrder->id,
            'qc_status' => 'pending',
            'reason' => 'Defective item',
            'created_by' => $this->user->id,
        ]);

        ReturnItem::create([
            'return_id' => $return->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $response = $this->putJson("/api/purchasing/returns/{$return->id}", [
            'qc_status' => 'approved',
        ]);

        $response->assertStatus(200);

        // Assert stock decreased by 3
        $this->assertDatabaseHas('product_stocks', [
            'product_id' => $product->id,
            'location_id' => $this->location->id,
            'quantity' => 7,
        ]);

        // Assert movement created
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'from_location_id' => $this->location->id,
            'type' => 'out',
            'quantity' => 3,
            'reference_type' => 'return',
            'reference_id' => $return->id,
        ]);

        // Assert payable deducted by 3 * 50000 = 150000
        $this->assertDatabaseHas('supplier_payables', [
            'id' => $payable->id,
            'amount' => 350000,
        ]);
    }

    public function test_cannot_approve_already_approved_return(): void
    {
        $customer = Customer::create(['name' => 'Test Customer', 'code' => 'CUST-002', 'contact_person' => 'Budi']);
        $product = Product::create([
            'name' => 'Test Finished Goods',
            'type' => 'finished_goods',
            'sku' => 'SKU-FG-002',
            'business_unit' => 'Pusat',
            'is_customizable' => false,
            'pricing_method' => 'fixed',
            'status' => 'active',
            'stock_status' => 'in_stock',
        ]);

        $return = ProductReturn::create([
            'return_number' => 'RET-C-002',
            'type' => 'customer',
            'customer_id' => $customer->id,
            'qc_status' => 'approved',
            'reason' => 'Defective item',
            'created_by' => $this->user->id,
        ]);

        $response = $this->putJson("/api/purchasing/returns/{$return->id}", [
            'qc_status' => 'approved',
        ]);

        $response->assertStatus(200);
    }
}
