<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Quotation;
use App\Models\Rfq;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\SupplierPayable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancellationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
    }

    // ══════════════════════════════════════════════════════════════
    //  QUOTATION
    // ══════════════════════════════════════════════════════════════

    public function test_cancel_quotation_changes_status_to_cancelled(): void
    {
        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();

        $quotation = Quotation::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'draft',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/sales/quotations/{$quotation->id}/cancel", [
                'reason' => 'Test pembatalan',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('quotations', [
            'id' => $quotation->id,
            'status' => 'cancelled',
            'cancelled_by' => $this->admin->id,
            'cancel_reason' => 'Test pembatalan',
        ]);
        $this->assertNotNull($quotation->fresh()->cancelled_at);
    }

    public function test_cancel_approved_quotation_returns_422(): void
    {
        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();

        $quotation = Quotation::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'approved',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/sales/quotations/{$quotation->id}/cancel")
            ->assertUnprocessable();
    }

    public function test_cancel_quotation_requires_authentication(): void
    {
        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();

        $quotation = Quotation::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'draft',
        ]);

        $this->postJson("/api/sales/quotations/{$quotation->id}/cancel", [], ['Authorization' => ''])
            ->assertUnauthorized();
    }

    // ══════════════════════════════════════════════════════════════
    //  SALES ORDER — GUARD KETAT
    // ══════════════════════════════════════════════════════════════

    /**
     * Guard: SO tidak bisa di-cancel jika masih ada DO aktif.
     */
    public function test_cannot_cancel_so_with_active_delivery_order(): void
    {
        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();

        $so = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'approved',
        ]);

        DeliveryOrder::factory()->create([
            'sales_order_id' => $so->id,
            'customer_id' => $customer->id,
            'status' => 'ready_to_load',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/sales/sales-orders/{$so->id}/cancel", ['reason' => 'Test'])
            ->assertUnprocessable();
    }

    /**
     * Guard: SO tidak bisa di-cancel jika masih ada Invoice aktif.
     */
    public function test_cannot_cancel_so_with_active_invoice(): void
    {
        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();

        $so = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'approved',
        ]);

        Invoice::factory()->create([
            'sales_order_id' => $so->id,
            'customer_id' => $customer->id,
            'status' => 'unpaid',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/sales/sales-orders/{$so->id}/cancel", ['reason' => 'Test'])
            ->assertUnprocessable();
    }

    /**
     * Happy path: SO bisa di-cancel setelah semua DO & Invoice sudah di-cancel.
     */
    public function test_can_cancel_so_after_all_children_cancelled(): void
    {
        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();

        $so = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'approved',
        ]);

        DeliveryOrder::factory()->create([
            'sales_order_id' => $so->id,
            'customer_id' => $customer->id,
            'status' => 'cancelled',
            'cancelled_by' => $this->admin->id,
            'cancelled_at' => now(),
        ]);

        Invoice::factory()->create([
            'sales_order_id' => $so->id,
            'customer_id' => $customer->id,
            'status' => 'cancelled',
            'cancelled_by' => $this->admin->id,
            'cancelled_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/sales/sales-orders/{$so->id}/cancel", ['reason' => 'Semua anak sudah di-cancel'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('sales_orders', [
            'id' => $so->id,
            'status' => 'cancelled',
            'cancelled_by' => $this->admin->id,
        ]);
    }

    /**
     * SO tanpa DO dan Invoice bisa langsung di-cancel.
     */
    public function test_can_cancel_so_without_children(): void
    {
        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();

        $so = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'draft',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/sales/sales-orders/{$so->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cancel_records_cancelled_by_user(): void
    {
        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();

        $so = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'draft',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/sales/sales-orders/{$so->id}/cancel");

        $this->assertDatabaseHas('sales_orders', [
            'id' => $so->id,
            'cancelled_by' => $this->admin->id,
        ]);
        $this->assertNotNull(SalesOrder::find($so->id)->cancelled_at);
    }

    public function test_cancelled_sales_orders_excluded_from_index(): void
    {
        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();

        $activeSo = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'approved',
        ]);

        $cancelledSo = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'cancelled',
            'cancelled_by' => $this->admin->id,
            'cancelled_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/sales/sales-orders')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->toArray();

        $this->assertContains($activeSo->id, $ids);
        $this->assertNotContains($cancelledSo->id, $ids);
    }

    // ══════════════════════════════════════════════════════════════
    //  DELIVERY ORDER — STOCK REVERSAL
    // ══════════════════════════════════════════════════════════════

    /**
     * Cancel DO yang sudah shipped → stok harus kembali.
     */
    public function test_cancel_shipped_delivery_order_reverses_stock(): void
    {
        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();
        $product = Product::query()->where('sku', 'PRC-0001')->firstOrFail();
        $location = StorageLocation::factory()->create();

        // Buat stock awal 10
        ProductStock::query()->firstOrCreate(
            ['product_id' => $product->id, 'location_id' => $location->id],
            ['quantity' => 10]
        );

        $so = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'approved',
        ]);

        $do = DeliveryOrder::factory()->create([
            'sales_order_id' => $so->id,
            'customer_id' => $customer->id,
            'status' => 'shipped',
        ]);

        $doItem = DeliveryOrderItem::factory()->create([
            'delivery_order_id' => $do->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        // Catat StockMovement out (simulasi pengiriman sebelumnya)
        StockMovement::query()->create([
            'product_id' => $product->id,
            'from_location_id' => $location->id,
            'to_location_id' => null,
            'type' => 'out',
            'quantity' => 5,
            'reference_type' => 'delivery_order',
            'reference_id' => $do->id,
            'reference_number' => $do->delivery_number,
            'handled_by' => $this->admin->id,
            'movement_at' => now(),
        ]);

        // Kurangi stok (simulasi stok setelah shipped: 10 - 5 = 5)
        ProductStock::query()
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->update(['quantity' => 5]);

        $this->actingAs($this->admin)
            ->postJson("/api/sales/delivery-orders/{$do->id}/cancel", ['reason' => 'Barang rusak'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        // Stok harus kembali ke 10
        $stock = ProductStock::query()
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->first();

        $this->assertEquals(10, (float) $stock->quantity);

        // StockMovement reversal harus tercatat
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'in',
            'reference_type' => 'do_reversal',
            'reference_id' => $do->id,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  INVOICE — GUARD KETAT
    // ══════════════════════════════════════════════════════════════

    /**
     * Guard: Invoice tidak bisa di-cancel jika masih ada Payment aktif.
     */
    public function test_cannot_cancel_invoice_with_active_payment(): void
    {
        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'partial',
        ]);

        Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'status' => 'verified',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/finance/invoices/{$invoice->id}/cancel", ['reason' => 'Test'])
            ->assertUnprocessable();
    }

    /**
     * Happy path: Invoice bisa di-cancel setelah semua Payment di-cancel.
     * SO tetap aktif → bisa buat Invoice baru.
     */
    public function test_can_cancel_invoice_after_payment_cancelled(): void
    {
        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();
        $so = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'approved',
        ]);

        $invoice = Invoice::factory()->create([
            'sales_order_id' => $so->id,
            'customer_id' => $customer->id,
            'status' => 'partial',
        ]);

        Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'status' => 'cancelled',
            'cancelled_by' => $this->admin->id,
            'cancelled_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/finance/invoices/{$invoice->id}/cancel", ['reason' => 'Dibuat ulang'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        // SO tetap aktif
        $this->assertDatabaseHas('sales_orders', [
            'id' => $so->id,
            'status' => 'approved',
        ]);
    }

    /**
     * Invoice tanpa payment bisa langsung di-cancel.
     */
    public function test_cancel_invoice_without_payments_succeeds(): void
    {
        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'unpaid',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/finance/invoices/{$invoice->id}/cancel", ['reason' => 'Kesalahan input'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    // ══════════════════════════════════════════════════════════════
    //  PAYMENT — REVERSE PAID_AMOUNT
    // ══════════════════════════════════════════════════════════════

    /**
     * Cancel Payment → paid_amount Invoice berkurang, Invoice kembali ke unpaid/partial.
     * Invoice tetap aktif → bisa di-billing ulang.
     */
    public function test_cancel_payment_reverses_invoice_paid_amount(): void
    {
        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'paid',
            'total' => 1000000,
            'paid_amount' => 1000000,
        ]);

        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => 1000000,
            'status' => 'verified',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/finance/payments/{$payment->id}/cancel", ['reason' => 'Pembayaran error'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        // paid_amount harus 0
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        // Invoice masih aktif (tidak cancelled)
        $this->assertDatabaseMissing('invoices', [
            'id' => $invoice->id,
            'status' => 'cancelled',
        ]);
    }

    /**
     * Cancel Payment partial → Invoice kembali ke status partial.
     */
    public function test_cancel_partial_payment_sets_invoice_back_to_partial(): void
    {
        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'paid',
            'total' => 1000000,
            'paid_amount' => 1000000,
        ]);

        // Ada 2 payment: 600k verified + 400k verified
        Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => 600000,
            'status' => 'verified',
        ]);

        $payment400 = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => 400000,
            'status' => 'verified',
        ]);

        // Cancel hanya payment 400k
        $this->actingAs($this->admin)
            ->postJson("/api/finance/payments/{$payment400->id}/cancel", ['reason' => 'Transfer salah'])
            ->assertOk();

        // paid_amount = 1.000.000 - 400.000 = 600.000 → partial
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'paid_amount' => 600000,
            'status' => 'partial',
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  PURCHASE ORDER — GUARD KETAT
    // ══════════════════════════════════════════════════════════════

    /**
     * Guard: PO tidak bisa di-cancel jika masih ada GRN aktif.
     */
    public function test_cannot_cancel_po_with_active_grn(): void
    {
        $supplier = Supplier::query()->first();

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'fully_received',
        ]);

        GoodsReceiptNote::factory()->create([
            'purchase_order_id' => $po->id,
            'status' => 'received',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/purchasing/purchase-orders/{$po->id}/cancel", ['reason' => 'Test'])
            ->assertUnprocessable();
    }

    /**
     * Guard: PO tidak bisa di-cancel jika masih ada SupplierPayable aktif.
     */
    public function test_cannot_cancel_po_with_active_supplier_payable(): void
    {
        $supplier = Supplier::query()->first();

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'ordered',
        ]);

        SupplierPayable::factory()->create([
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'status' => 'open',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/purchasing/purchase-orders/{$po->id}/cancel", ['reason' => 'Test'])
            ->assertUnprocessable();
    }

    /**
     * Happy path: PO bisa di-cancel setelah GRN & Payable di-cancel.
     */
    public function test_can_cancel_po_after_grn_and_payable_cancelled(): void
    {
        $supplier = Supplier::query()->first();

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'ordered',
        ]);

        GoodsReceiptNote::factory()->create([
            'purchase_order_id' => $po->id,
            'status' => 'cancelled',
            'cancelled_by' => $this->admin->id,
            'cancelled_at' => now(),
        ]);

        SupplierPayable::factory()->create([
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'status' => 'cancelled',
            'cancelled_by' => $this->admin->id,
            'cancelled_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/purchasing/purchase-orders/{$po->id}/cancel", ['reason' => 'Supplier tidak tersedia'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    // ══════════════════════════════════════════════════════════════
    //  GRN — STOCK REVERSAL
    // ══════════════════════════════════════════════════════════════

    /**
     * Cancel GRN → stok harus kembali ke nilai sebelumnya.
     */
    public function test_cancel_grn_reverses_stock(): void
    {
        $supplier = Supplier::query()->first();
        $product = Product::query()->where('sku', 'PRC-0001')->firstOrFail();
        $location = StorageLocation::factory()->create();

        // Simulasi stok setelah GRN masuk: 20
        ProductStock::query()->firstOrCreate(
            ['product_id' => $product->id, 'location_id' => $location->id],
            ['quantity' => 20]
        );

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'fully_received',
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'received_qty' => 20,
        ]);

        $grn = GoodsReceiptNote::factory()->create([
            'purchase_order_id' => $po->id,
            'to_location_id' => $location->id,
            'status' => 'received',
        ]);

        GoodsReceiptNoteItem::factory()->create([
            'goods_receipt_note_id' => $grn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $product->id,
            'received_qty' => 20,
            'rejected_qty' => 0,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/purchasing/goods-receipt-notes/{$grn->id}/cancel", ['reason' => 'GRN salah item'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        // Stok harus kembali ke 0 (20 - 20 = 0)
        $stock = ProductStock::query()
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->first();

        $this->assertEquals(0, (float) $stock->quantity);

        // received_qty di PO Item harus berkurang
        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $poItem->id,
            'received_qty' => 0,
        ]);

        // StockMovement reversal harus tercatat
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'out',
            'reference_type' => 'grn_reversal',
            'reference_id' => $grn->id,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  RFQ & PR — GUARD KETAT
    // ══════════════════════════════════════════════════════════════

    /**
     * Guard: RFQ tidak bisa di-cancel jika masih ada PO aktif.
     */
    public function test_cannot_cancel_rfq_with_active_po(): void
    {
        $supplier = Supplier::query()->first();

        $rfq = Rfq::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'sent',
        ]);

        PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'rfq_id' => $rfq->id,
            'status' => 'ordered',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/purchasing/rfqs/{$rfq->id}/cancel", ['reason' => 'Test'])
            ->assertUnprocessable();
    }

    /**
     * SupplierPayable dapat di-cancel mandiri.
     */
    public function test_cancel_supplier_payable_standalone(): void
    {
        $supplier = Supplier::query()->first();

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'ordered',
        ]);

        $payable = SupplierPayable::factory()->create([
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'status' => 'open',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/purchasing/supplier-payables/{$payable->id}/cancel", ['reason' => 'Duplikat'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        // PO tetap aktif
        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => 'ordered',
        ]);
    }
}
