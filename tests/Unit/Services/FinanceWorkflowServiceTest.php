<?php

namespace Tests\Unit\Services;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierPayable;
use App\Models\User;
use App\Services\FinanceWorkflowService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FinanceWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    protected FinanceWorkflowService $service;

    protected User $admin;

    protected Account $account;

    protected Customer $customer;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        Model::unguard();

        $this->service = app(FinanceWorkflowService::class);

        $this->admin = User::forceCreate([
            'name' => 'Admin Test',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $this->account = Account::forceCreate([
            'code' => 'ACC-01',
            'name' => 'Kas Test',
            'type' => 'cash',
            'is_active' => true,
            'currency' => 'IDR',
            'balance' => 1000000,
        ]);

        $this->customer = Customer::forceCreate([
            'code' => 'CUST-UMUM',
            'name' => 'Pelanggan Umum',
            'email' => 'umum@example.com',
            'phone' => '08123456789',
            'status' => 'active',
        ]);

        $this->supplier = Supplier::forceCreate([
            'code' => 'SUP-01',
            'name' => 'Supplier Test',
            'status' => 'active',
        ]);
    }

    public function test_verify_payment_updates_invoice_and_records_cash()
    {
        $invoice = Invoice::forceCreate([
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-001',
            'invoice_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d'),
            'total' => 500000,
            'paid_amount' => 100000,
            'status' => 'partial',
        ]);

        $payment = Payment::forceCreate([
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-001',
            'payment_date' => date('Y-m-d'),
            'amount' => 400000,
            'method' => 'cash',
            'status' => 'pending',
            'account_id' => $this->account->id,
        ]);

        $verifiedPayment = $this->service->verifyPayment($payment->id, [
            'verified_by' => $this->admin->id,
            'notes' => 'Lunas',
        ]);

        $this->assertEquals('verified', $verifiedPayment->status);
        $this->assertEquals($this->admin->id, $verifiedPayment->verified_by);

        $invoice->refresh();
        $this->assertEquals(500000, $invoice->paid_amount);
        $this->assertEquals('paid', $invoice->status);

        $this->account->refresh();
        $this->assertEquals(1400000, $this->account->balance); // 1000000 + 400000

        $this->assertDatabaseHas('cash_transactions', [
            'account_id' => $this->account->id,
            'amount' => 400000,
            'type' => 'in',
            'reference_type' => 'App\Models\Payment',
            'reference_id' => $payment->id,
        ]);
    }

    public function test_verify_payment_throws_if_amount_exceeds_balance()
    {
        $invoice = Invoice::forceCreate([
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-002',
            'invoice_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d'),
            'total' => 500000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        $payment = Payment::forceCreate([
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-002',
            'payment_date' => date('Y-m-d'),
            'amount' => 600000,
            'method' => 'cash',
            'status' => 'pending',
            'account_id' => $this->account->id,
        ]);

        $this->expectException(HttpException::class);
        $this->service->verifyPayment($payment->id, []);
    }

    public function test_pay_supplier_payable_updates_ap_and_records_cash()
    {
        $po = PurchaseOrder::forceCreate([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-001',
            'po_date' => date('Y-m-d'),
            'total' => 2000000,
            'status' => 'completed',
        ]);

        $payable = SupplierPayable::forceCreate([
            'supplier_id' => $this->supplier->id,
            'purchase_order_id' => $po->id,
            'payable_number' => 'AP-001',
            'due_date' => date('Y-m-d'),
            'amount' => 2000000,
            'paid_amount' => 0,
            'status' => 'open',
        ]);

        $paidPayable = $this->service->paySupplierPayable($payable->id, [
            'amount' => 1500000,
            'account_id' => $this->account->id,
            'paid_at' => date('Y-m-d'),
            'notes' => 'Cicilan',
        ]);

        $this->assertEquals(1500000, $paidPayable->paid_amount);
        $this->assertEquals('partial', $paidPayable->status);

        $this->account->refresh();
        $this->assertEquals(-500000, $this->account->balance);

        $this->assertDatabaseHas('cash_transactions', [
            'account_id' => $this->account->id,
            'amount' => 1500000,
            'type' => 'out',
            'reference_type' => 'App\Models\SupplierPayable',
            'reference_id' => $payable->id,
        ]);
    }
}
