<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Project;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_and_item_can_be_created_listed_and_updated(): void
    {
        $this->seed();

        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();
        $salesOrder = SalesOrder::query()->where('order_number', 'SO-INIT')->firstOrFail();
        $project = Project::query()->where('code', 'PRJ-INIT')->firstOrFail();
        $product = Product::query()->where('sku', 'PRC-0001')->firstOrFail();

        $invoiceResponse = $this->postJson('/api/finance/invoices', [
            'sales_order_id' => $salesOrder->id,
            'project_id' => $project->id,
            'invoice_number' => 'INV-API-001',
            'customer_id' => $customer->id,
            'invoice_date' => '2026-06-05',
            'due_date' => '2026-06-20',
            'subtotal' => 1000000,
            'tax_amount' => 110000,
            'total' => 1110000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        $invoiceResponse
            ->assertCreated()
            ->assertJsonPath('data.invoice_number', 'INV-API-001')
            ->assertJsonPath('data.customer.code', 'CUST-UMUM')
            ->assertJsonPath('data.project.code', 'PRJ-INIT');

        $invoiceId = $invoiceResponse->json('data.id');

        $this->postJson('/api/finance/invoice-items', [
            'invoice_id' => $invoiceId,
            'product_id' => $product->id,
            'description' => 'API invoice item',
            'quantity' => 1,
            'unit_price' => 1000000,
            'subtotal' => 1000000,
        ])
            ->assertCreated()
            ->assertJsonPath('data.invoice.invoice_number', 'INV-API-001')
            ->assertJsonPath('data.product.sku', 'PRC-0001');

        $this->getJson('/api/finance/invoices?q=INV-API')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->patchJson("/api/finance/invoices/{$invoiceId}", [
            'paid_amount' => 500000,
            'status' => 'partial',
        ])
            ->assertOk()
            ->assertJsonPath('data.paid_amount', '500000.00')
            ->assertJsonPath('data.status', 'partial');
    }

    public function test_payment_can_be_created_for_invoice(): void
    {
        $this->seed();

        $invoice = Invoice::query()->where('invoice_number', 'INV-INIT')->firstOrFail();
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->postJson('/api/finance/payments', [
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-API-001',
            'payment_date' => '2026-06-05 10:00:00',
            'method' => 'transfer',
            'amount' => 250000,
            'status' => 'verified',
            'verified_by' => $admin->id,
            'verified_at' => '2026-06-05 10:30:00',
            'notes' => 'API payment.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.payment_number', 'PAY-API-001')
            ->assertJsonPath('data.invoice.invoice_number', 'INV-INIT')
            ->assertJsonPath('data.verified_by.email', 'admin@example.com');
    }

    public function test_project_termin_can_be_created_and_linked_to_invoice(): void
    {
        $this->seed();

        $project = Project::query()->where('code', 'PRJ-INIT')->firstOrFail();
        $invoice = Invoice::query()->where('invoice_number', 'INV-INIT')->firstOrFail();

        $this->postJson('/api/finance/project-termins', [
            'project_id' => $project->id,
            'phase' => 'Termin API',
            'amount' => 500000,
            'due_date' => '2026-06-25',
            'status' => 'unpaid',
            'invoice_id' => $invoice->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.phase', 'Termin API')
            ->assertJsonPath('data.project.code', 'PRJ-INIT')
            ->assertJsonPath('data.invoice.invoice_number', 'INV-INIT');
    }

    public function test_finance_api_rejects_invalid_payment_method(): void
    {
        $this->seed();

        $invoice = Invoice::query()->where('invoice_number', 'INV-INIT')->firstOrFail();

        $this->postJson('/api/finance/payments', [
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-API-BAD',
            'payment_date' => '2026-06-05',
            'method' => 'card',
            'amount' => 100000,
        ])->assertUnprocessable();
    }
}
