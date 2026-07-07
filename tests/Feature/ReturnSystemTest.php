<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CashTransaction;
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
use App\Models\StockMovement;
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

        $response = $this->putJson("/api/returns/{$return->id}", [
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

    public function test_approve_customer_return_from_pos_auto_refunds_cash(): void
    {
        $customer = Customer::create(['name' => 'Test POS Customer', 'code' => 'CUST-POS', 'contact_person' => 'Budi']);
        $product = Product::create([
            'name' => 'Test POS Product',
            'type' => 'finished_good',
            'sku' => 'SKU-POS-001',
            'business_unit' => 'Pusat',
            'is_customizable' => false,
            'pricing_method' => 'fixed',
            'status' => 'active',
            'stock_status' => 'in_stock',
        ]);

        $salesOrder = SalesOrder::create([
            'customer_id' => $customer->id,
            'order_number' => 'POS-SO-001',
            'order_date' => now()->toDateString(),
            'total' => 500000,
            'status' => 'completed',
            'source' => 'pos', // Source POS!
        ]);

        SalesOrderItem::create([
            'sales_order_id' => $salesOrder->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 100000,
            'subtotal' => 500000,
        ]);

        $invoice = Invoice::create([
            'sales_order_id' => $salesOrder->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'POS-INV-001',
            'invoice_date' => now()->toDateString(),
            'subtotal' => 500000,
            'tax_amount' => 0,
            'total' => 500000,
            'paid_amount' => 500000,
            'status' => 'paid',
        ]);

        $account = Account::create([
            'name' => 'Kasir Utama',
            'code' => 'KAS-01',
            'type' => 'asset',
            'category' => 'cash',
        ]);

        // Mock original cash in from POS
        CashTransaction::create([
            'account_id' => $account->id,
            'transaction_number' => 'CASH-IN-TEST',
            'transaction_date' => now()->toDateString(),
            'type' => 'in',
            'amount' => 500000,
            'category' => 'sales',
            'reference_type' => 'invoice',
            'reference_id' => $invoice->id,
        ]);

        $return = ProductReturn::create([
            'return_number' => 'RTN-POS-001',
            'date' => now()->toDateString(),
            'type' => 'customer',
            'sales_order_id' => $salesOrder->id,
            'customer_id' => $customer->id,
            'reason' => 'Defective',
            'qc_status' => 'pending',
        ]);

        ReturnItem::create([
            'return_id' => $return->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response = $this->putJson("/api/returns/{$return->id}", [
            'qc_status' => 'approved',
        ]);

        $response->assertStatus(200);

        // 500000 - 200000 = 300000 (total)
        // Auto refund should kick in!

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'total' => 300000,
            'paid_amount' => 300000, // Should be balanced
            'status' => 'paid',
        ]);

        // Check if cash out was created
        $this->assertDatabaseHas('cash_transactions', [
            'account_id' => $account->id,
            'type' => 'out',
            'amount' => 200000, // Refund amount
            'category' => 'sales',
            'reference_type' => 'return',
            'reference_id' => $return->id,
        ]);
    }

    public function test_manual_refund_overpayment(): void
    {
        $customer = Customer::create(['name' => 'Test Normal Customer', 'code' => 'CUST-NORM', 'contact_person' => 'Joko']);
        $product = Product::create([
            'name' => 'Test Normal Product',
            'type' => 'finished_good',
            'sku' => 'SKU-NORM-001',
            'business_unit' => 'Pusat',
            'is_customizable' => false,
            'pricing_method' => 'fixed',
            'status' => 'active',
            'stock_status' => 'in_stock',
        ]);

        $salesOrder = SalesOrder::create([
            'customer_id' => $customer->id,
            'order_number' => 'SO-NORM-001',
            'order_date' => now()->toDateString(),
            'total' => 500000,
            'status' => 'completed',
            'source' => 'web', // Not POS
        ]);

        SalesOrderItem::create([
            'sales_order_id' => $salesOrder->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 100000,
            'subtotal' => 500000,
        ]);

        $invoice = Invoice::create([
            'sales_order_id' => $salesOrder->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-NORM-001',
            'invoice_date' => now()->toDateString(),
            'subtotal' => 500000,
            'tax_amount' => 0,
            'total' => 500000,
            'paid_amount' => 500000, // Fully paid
            'status' => 'paid',
        ]);

        $account = Account::create([
            'name' => 'Bank BCA',
            'code' => 'BCA-01',
            'type' => 'asset',
            'category' => 'bank',
        ]);

        $return = ProductReturn::create([
            'return_number' => 'RTN-NORM-001',
            'date' => now()->toDateString(),
            'type' => 'customer',
            'sales_order_id' => $salesOrder->id,
            'customer_id' => $customer->id,
            'reason' => 'Defective',
            'qc_status' => 'pending',
        ]);

        ReturnItem::create([
            'return_id' => $return->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        // First approve the return
        $response = $this->putJson("/api/returns/{$return->id}", [
            'qc_status' => 'approved',
        ]);
        $response->assertStatus(200);

        // After approval, invoice total is 300000, paid_amount is 500000 (overpaid)
        // Now trigger manual refund
        $refundResponse = $this->postJson("/api/returns/{$return->id}/refund", [
            'account_id' => $account->id,
        ]);

        $refundResponse->assertStatus(200);

        // Check if invoice paid_amount is balanced
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'total' => 300000,
            'paid_amount' => 300000,
            'status' => 'paid',
        ]);

        // Check cash out transaction created
        $this->assertDatabaseHas('cash_transactions', [
            'account_id' => $account->id,
            'type' => 'out',
            'amount' => 200000,
            'reference_type' => 'return',
            'reference_id' => $return->id,
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

        $response = $this->putJson("/api/returns/{$return->id}", [
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

        $response = $this->putJson("/api/returns/{$return->id}", [
            'qc_status' => 'approved',
        ]);

        $response->assertStatus(200);
    }

    public function test_claim_to_supplier_creates_new_supplier_return(): void
    {
        $supplier = Supplier::create(['name' => 'Supplier for Claim', 'code' => 'SUP-CLM', 'contact_person' => 'Agus']);
        $product = Product::create([
            'name' => 'Product for Claim',
            'type' => 'finished_goods',
            'sku' => 'SKU-CLM-001',
            'business_unit' => 'Pusat',
            'is_customizable' => false,
            'pricing_method' => 'fixed',
            'status' => 'active',
            'stock_status' => 'in_stock',
        ]);

        $purchaseOrder = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-CLM-001',
            'po_date' => now()->format('Y-m-d'),
            'total_amount' => 500000,
            'status' => 'Completed',
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 50000,
            'subtotal' => 500000,
        ]);

        $customer = Customer::create(['name' => 'Customer for Claim', 'code' => 'CUST-CLM', 'contact_person' => 'Budi']);
        $return = ProductReturn::create([
            'return_number' => 'RET-C-CLM',
            'type' => 'customer',
            'customer_id' => $customer->id,
            'qc_status' => 'approved',
            'reason' => 'Defective item',
            'created_by' => $this->user->id,
        ]);

        ReturnItem::create([
            'return_id' => $return->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response = $this->putJson("/api/returns/{$return->id}", [
            'qc_status' => 'supplier_claim',
        ]);

        $response->assertStatus(200);

        // Assert original return is updated
        $this->assertDatabaseHas('returns', [
            'id' => $return->id,
            'qc_status' => 'supplier_claim',
        ]);

        // Assert new supplier return is created
        $this->assertDatabaseHas('returns', [
            'type' => 'supplier',
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $purchaseOrder->id,
            'qc_status' => 'pending_qc',
        ]);
    }

    public function test_approve_supplier_return_replacement_creates_po_and_does_not_deduct_payable(): void
    {
        $supplier = Supplier::create(['name' => 'Test Supplier Repl', 'code' => 'SUP-002', 'contact_person' => 'Agus']);
        $product = Product::create([
            'name' => 'Test Raw Material 2',
            'type' => 'raw_material',
            'sku' => 'SKU-RM-002',
            'business_unit' => 'Pusat',
            'is_customizable' => false,
            'pricing_method' => 'fixed',
            'status' => 'active',
            'stock_status' => 'in_stock',
        ]);

        ProductStock::create([
            'product_id' => $product->id,
            'location_id' => $this->location->id,
            'quantity' => 10,
        ]);

        $purchaseOrder = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-TEST-002',
            'po_date' => now()->format('Y-m-d'),
            'total' => 500000,
            'status' => 'Completed',
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 50000,
            'subtotal' => 500000,
        ]);

        $payable = SupplierPayable::create([
            'payable_number' => 'AP-TEST-002',
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $supplier->id,
            'amount' => 500000,
            'paid_amount' => 0,
            'status' => 'open',
            'due_date' => now()->addDays(7)->format('Y-m-d'),
        ]);

        $return = ProductReturn::create([
            'return_number' => 'RET-S-002',
            'type' => 'supplier',
            'action' => 'replace', // Replace action!
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

        $response = $this->putJson("/api/returns/{$return->id}", [
            'qc_status' => 'approved',
        ]);

        $response->assertStatus(200);

        // Assert stock decreased by 3
        $this->assertDatabaseHas('product_stocks', [
            'product_id' => $product->id,
            'location_id' => $this->location->id,
            'quantity' => 7,
        ]);

        // Assert payable is NOT deducted (still 500000)
        $this->assertDatabaseHas('supplier_payables', [
            'id' => $payable->id,
            'amount' => 500000,
        ]);

        // Assert new draft PO created with 0 total
        $this->assertDatabaseHas('purchase_orders', [
            'supplier_id' => $supplier->id,
            'status' => 'Draft',
            'total' => 0,
        ]);

        $newPo = PurchaseOrder::where('supplier_id', $supplier->id)->where('status', 'Draft')->where('total', 0)->first();
        $this->assertNotNull($newPo);

        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $newPo->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 0,
        ]);
    }

    public function test_approve_customer_return_fails_if_stock_insufficient_for_replacement(): void
    {
        $customer = Customer::create(['name' => 'Test Customer', 'email' => 'test@cust.com']);
        $salesOrder = SalesOrder::create([
            'customer_id' => $customer->id,
            'order_number' => 'SO-TEST-004',
            'order_date' => now()->format('Y-m-d'),
            'total_amount' => 500000,
            'status' => 'completed',
        ]);

        $product = Product::create([
            'name' => 'Test Item Insufficient',
            'type' => 'finished_good',
            'sku' => 'SKU-INSUFF-01',
            'business_unit' => 'Pusat',
            'is_customizable' => false,
            'pricing_method' => 'fixed',
            'status' => 'active',
            'stock_status' => 'in_stock',
        ]);

        ProductStock::create([
            'product_id' => $product->id,
            'location_id' => $this->location->id,
            'quantity' => 1, // Only 1 in stock!
        ]);

        $return = ProductReturn::create([
            'return_number' => 'RET-INSUFF-001',
            'type' => 'customer',
            'action' => 'replace',
            'customer_id' => $customer->id,
            'sales_order_id' => $salesOrder->id,
            'qc_status' => 'pending',
            'reason' => 'Defective item',
            'created_by' => $this->user->id,
        ]);

        ReturnItem::create([
            'return_id' => $return->id,
            'product_id' => $product->id,
            'quantity' => 5, // Requires 5 for replacement!
        ]);

        $response = $this->putJson("/api/returns/{$return->id}", [
            'qc_status' => 'approved',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => "Stok tidak mencukupi untuk barang pengganti ({$product->name}). Tersedia: 1, Dibutuhkan: 5",
            ]);
    }

    public function test_approve_customer_return_succeeds_if_stock_insufficient_but_allow_backorder_is_true(): void
    {
        $customer = Customer::create(['name' => 'Test Customer', 'email' => 'test@cust.com']);
        $salesOrder = SalesOrder::create([
            'customer_id' => $customer->id,
            'order_number' => 'SO-TEST-005',
            'order_date' => now()->format('Y-m-d'),
            'total_amount' => 500000,
            'status' => 'completed',
        ]);

        $product = Product::create([
            'name' => 'Test Item Insufficient 2',
            'type' => 'finished_good',
            'sku' => 'SKU-INSUFF-02',
            'business_unit' => 'Pusat',
            'is_customizable' => false,
            'pricing_method' => 'fixed',
            'status' => 'active',
            'stock_status' => 'in_stock',
        ]);

        ProductStock::create([
            'product_id' => $product->id,
            'location_id' => $this->location->id,
            'quantity' => 1,
        ]);

        $return = ProductReturn::create([
            'return_number' => 'RET-INSUFF-002',
            'type' => 'customer',
            'action' => 'replace',
            'customer_id' => $customer->id,
            'sales_order_id' => $salesOrder->id,
            'qc_status' => 'pending',
            'reason' => 'Defective item',
            'created_by' => $this->user->id,
        ]);

        ReturnItem::create([
            'return_id' => $return->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $response = $this->putJson("/api/returns/{$return->id}", [
            'qc_status' => 'approved',
            'allow_backorder' => true,
        ]);

        $response->assertStatus(200);
        $this->assertEquals('approved', $return->fresh()->qc_status);

        // Verify DO is generated with status Draft
        $this->assertDatabaseHas('delivery_orders', [
            'sales_order_id' => $salesOrder->id,
            'status' => 'draft',
        ]);
    }

    public function test_approve_customer_return_replacement_creates_do_and_resolves_original_location(): void
    {
        $customer = Customer::create(['name' => 'Test Customer', 'email' => 'test@cust.com']);
        $salesOrder = SalesOrder::create([
            'customer_id' => $customer->id,
            'order_number' => 'SO-TEST-006',
            'order_date' => now()->format('Y-m-d'),
            'total_amount' => 500000,
            'status' => 'completed',
        ]);

        $product = Product::create([
            'name' => 'Test Item Repl',
            'type' => 'finished_good',
            'sku' => 'SKU-REPL-01',
            'business_unit' => 'Pusat',
            'is_customizable' => false,
            'pricing_method' => 'fixed',
            'status' => 'active',
            'stock_status' => 'in_stock',
        ]);

        $originalLocation = StorageLocation::factory()->create([
            'name' => 'Gudang Lama',
            'code' => 'GD-LAMA',
        ]);

        // Add enough stock for replacement
        ProductStock::create([
            'product_id' => $product->id,
            'location_id' => $this->location->id,
            'quantity' => 10,
        ]);

        // Mock original movement out from Gudang Lama
        StockMovement::create([
            'product_id' => $product->id,
            'from_location_id' => $originalLocation->id,
            'to_location_id' => null,
            'type' => 'out',
            'quantity' => 5,
            'reference_type' => 'pos',
            'reference_id' => $salesOrder->id,
            'movement_at' => now(),
            'notes' => 'Original Sale',
            'handled_by' => $this->user->id,
        ]);

        $return = ProductReturn::create([
            'return_number' => 'RET-REPL-001',
            'type' => 'customer',
            'action' => 'replace',
            'customer_id' => $customer->id,
            'sales_order_id' => $salesOrder->id,
            'qc_status' => 'pending',
            'reason' => 'Defective item',
            'created_by' => $this->user->id,
        ]);

        ReturnItem::create([
            'return_id' => $return->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $response = $this->putJson("/api/returns/{$return->id}", [
            'qc_status' => 'approved',
        ]);

        $response->assertStatus(200);

        // Verify DO is generated
        $this->assertDatabaseHas('delivery_orders', [
            'sales_order_id' => $salesOrder->id,
            'status' => 'draft',
        ]);

        // Verify stock returned to originalLocation (Gudang Lama)
        $this->assertDatabaseHas('product_stocks', [
            'product_id' => $product->id,
            'location_id' => $originalLocation->id,
            'quantity' => 5,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'to_location_id' => $originalLocation->id,
            'type' => 'in',
            'reference_type' => 'return',
            'reference_id' => $return->id,
        ]);
    }

    public function test_reject_customer_return_changes_status_only(): void
    {
        $customer = Customer::create(['name' => 'Test Customer', 'email' => 'test@cust.com']);
        $product = Product::create([
            'name' => 'Test Item Reject',
            'type' => 'finished_good',
            'sku' => 'SKU-REJ-01',
            'business_unit' => 'Pusat',
            'status' => 'active',
            'stock_status' => 'in_stock',
        ]);

        $return = ProductReturn::create([
            'return_number' => 'RET-REJ-001',
            'type' => 'customer',
            'action' => 'refund',
            'customer_id' => $customer->id,
            'qc_status' => 'pending',
            'reason' => 'Defective item',
            'created_by' => $this->user->id,
        ]);

        ReturnItem::create([
            'return_id' => $return->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response = $this->putJson("/api/returns/{$return->id}", [
            'qc_status' => 'rejected',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('rejected', $return->fresh()->qc_status);

        // Verify stock is untouched
        $this->assertDatabaseMissing('product_stocks', [
            'product_id' => $product->id,
        ]);

        $this->assertDatabaseMissing('stock_movements', [
            'reference_type' => 'return',
            'reference_id' => $return->id,
        ]);
    }
}
