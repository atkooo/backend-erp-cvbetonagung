<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierPayable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpFinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_seed_creates_invoice_payment_and_payable_documents(): void
    {
        $this->seed();

        $this->assertDatabaseHas('invoices', ['invoice_number' => 'INV-INIT']);
        $this->assertDatabaseHas('invoice_items', ['subtotal' => 1000000]);
        $this->assertDatabaseHas('payments', ['payment_number' => 'PAY-INIT']);
        $this->assertDatabaseHas('project_termins', ['phase' => 'Termin 1']);
        $this->assertDatabaseHas('purchase_orders', ['po_number' => 'PO-INIT']);
        $this->assertDatabaseHas('supplier_payables', ['payable_number' => 'PAYABLE-INIT']);
    }

    public function test_invoice_relations_are_available(): void
    {
        $this->seed();

        $invoice = Invoice::query()->where('invoice_number', 'INV-INIT')->firstOrFail();

        $this->assertSame('SO-INIT', $invoice->salesOrder?->order_number);
        $this->assertSame('PRJ-INIT', $invoice->project?->code);
        $this->assertSame('CUST-UMUM', $invoice->customer->code);
        $this->assertSame('PRC-0001', $invoice->items->first()?->product?->sku);
        $this->assertSame('PAY-INIT', $invoice->payments->first()?->payment_number);
        $this->assertSame('1000000.00', $invoice->total);
    }

    public function test_payment_and_project_termin_relations_are_available(): void
    {
        $this->seed();

        $payment = Payment::query()->where('payment_number', 'PAY-INIT')->firstOrFail();
        $project = Project::query()->where('code', 'PRJ-INIT')->firstOrFail();

        $this->assertSame('INV-INIT', $payment->invoice->invoice_number);
        $this->assertSame('admin@example.com', $payment->verifiedBy?->email);
        $this->assertTrue($project->termins->contains('phase', 'Termin 1'));
        $this->assertTrue($project->invoices->contains('invoice_number', 'INV-INIT'));
    }

    public function test_purchase_order_and_supplier_payable_relations_are_available(): void
    {
        $this->seed();

        $purchaseOrder = PurchaseOrder::query()->where('po_number', 'PO-INIT')->firstOrFail();
        $payable = SupplierPayable::query()->where('payable_number', 'PAYABLE-INIT')->firstOrFail();
        $supplier = Supplier::query()->where('code', 'SUP-UMUM')->firstOrFail();

        $this->assertSame('SUP-UMUM', $purchaseOrder->supplier->code);
        $this->assertSame('MTL-0001', $purchaseOrder->items->first()?->product?->sku);
        $this->assertSame('PO-INIT', $payable->purchaseOrder?->po_number);
        $this->assertSame('SUP-UMUM', $payable->supplier->code);
        $this->assertTrue($supplier->supplierPayables->contains('payable_number', 'PAYABLE-INIT'));
    }
}
