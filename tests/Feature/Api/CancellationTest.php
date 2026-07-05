<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\SalesOrder;
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

    // ── Quotation ──────────────────────────────────────────────

    public function test_cancel_quotation_changes_status_to_cancelled(): void
    {
        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();

        /** @var Quotation $quotation */
        $quotation = Quotation::factory()->create([
            'customer_id' => $customer->id,
            'status'      => 'draft',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/sales/quotations/{$quotation->id}/cancel", [
                'reason' => 'Test pembatalan',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('quotations', [
            'id'            => $quotation->id,
            'status'        => 'cancelled',
            'cancelled_by'  => $this->admin->id,
            'cancel_reason' => 'Test pembatalan',
        ]);
        $this->assertNotNull($quotation->fresh()->cancelled_at);
    }

    public function test_cancel_approved_quotation_returns_422(): void
    {
        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();

        $quotation = Quotation::factory()->create([
            'customer_id' => $customer->id,
            'status'      => 'approved',
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
            'status'      => 'draft',
        ]);

        $this->postJson("/api/sales/quotations/{$quotation->id}/cancel", [], ['Authorization' => ''])
            ->assertUnauthorized();
    }

    // ── Sales Order ────────────────────────────────────────────

    public function test_cancel_sales_order_cascades_to_delivery_and_invoice(): void
    {
        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();
        $product  = Product::query()->where('sku', 'PRC-0001')->firstOrFail();

        /** @var SalesOrder $so */
        $so = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'status'      => 'approved',
        ]);

        /** @var DeliveryOrder $do */
        $do = DeliveryOrder::factory()->create([
            'sales_order_id' => $so->id,
            'customer_id'    => $customer->id,
            'status'         => 'ready_to_load',
        ]);

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->create([
            'sales_order_id' => $so->id,
            'customer_id'    => $customer->id,
            'status'         => 'unpaid',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/sales/sales-orders/{$so->id}/cancel", [
                'reason' => 'Pelanggan membatalkan pesanan',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        // SO cancelled
        $this->assertDatabaseHas('sales_orders', [
            'id'     => $so->id,
            'status' => 'cancelled',
        ]);

        // DO cascade cancelled
        $this->assertDatabaseHas('delivery_orders', [
            'id'     => $do->id,
            'status' => 'cancelled',
        ]);

        // Invoice cascade cancelled
        $this->assertDatabaseHas('invoices', [
            'id'     => $invoice->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cancel_sales_order_fails_when_invoice_is_paid(): void
    {
        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();

        $so = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'status'      => 'approved',
        ]);

        Invoice::factory()->create([
            'sales_order_id' => $so->id,
            'customer_id'    => $customer->id,
            'status'         => 'paid',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/sales/sales-orders/{$so->id}/cancel")
            ->assertUnprocessable();

        // SO harus tetap tidak berubah
        $this->assertDatabaseHas('sales_orders', [
            'id'     => $so->id,
            'status' => 'approved',
        ]);
    }

    public function test_cancel_sales_order_skips_shipped_delivery_order(): void
    {
        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();

        $so = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'status'      => 'approved',
        ]);

        $shippedDo = DeliveryOrder::factory()->create([
            'sales_order_id' => $so->id,
            'customer_id'    => $customer->id,
            'status'         => 'shipped',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/sales/sales-orders/{$so->id}/cancel")
            ->assertOk();

        // DO yang sudah shipped tidak berubah
        $this->assertDatabaseHas('delivery_orders', [
            'id'     => $shippedDo->id,
            'status' => 'shipped',
        ]);
    }

    public function test_cancelled_sales_orders_excluded_from_index(): void
    {
        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();

        $activeSo = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'status'      => 'approved',
        ]);

        $cancelledSo = SalesOrder::factory()->create([
            'customer_id'  => $customer->id,
            'status'       => 'cancelled',
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

    // ── Invoice ────────────────────────────────────────────────

    public function test_cancel_invoice_cascades_to_pending_payments(): void
    {
        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status'      => 'unpaid',
        ]);

        /** @var Payment $payment */
        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'status'     => 'pending',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/finance/invoices/{$invoice->id}/cancel", [
                'reason' => 'Kesalahan penginputan',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('invoices', [
            'id'            => $invoice->id,
            'status'        => 'cancelled',
            'cancelled_by'  => $this->admin->id,
            'cancel_reason' => 'Kesalahan penginputan',
        ]);

        $this->assertDatabaseHas('payments', [
            'id'     => $payment->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cancel_paid_invoice_returns_422(): void
    {
        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status'      => 'paid',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/finance/invoices/{$invoice->id}/cancel")
            ->assertUnprocessable();
    }

    // ── Purchase Order ─────────────────────────────────────────

    public function test_cancel_purchase_order_cascades_to_supplier_payable(): void
    {
        $supplier = Supplier::query()->first();

        /** @var PurchaseOrder $po */
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status'      => 'ordered',
        ]);

        /** @var SupplierPayable $payable */
        $payable = SupplierPayable::factory()->create([
            'purchase_order_id' => $po->id,
            'supplier_id'       => $supplier->id,
            'status'            => 'open',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/purchasing/purchase-orders/{$po->id}/cancel", [
                'reason' => 'Supplier tidak tersedia',
            ])
            ->assertOk();

        $this->assertDatabaseHas('purchase_orders', [
            'id'     => $po->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('supplier_payables', [
            'id'     => $payable->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cancel_records_cancelled_by_user(): void
    {
        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();

        $so = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'status'      => 'draft',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/sales/sales-orders/{$so->id}/cancel");

        $this->assertDatabaseHas('sales_orders', [
            'id'           => $so->id,
            'cancelled_by' => $this->admin->id,
        ]);
        $this->assertNotNull(SalesOrder::find($so->id)->cancelled_at);
    }
}
