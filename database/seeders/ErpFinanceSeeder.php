<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class ErpFinanceSeeder extends Seeder
{
    /**
     * Seed minimal finance and payable documents.
     */
    public function run(): void
    {
        $salesOrder = SalesOrder::query()->where('order_number', 'SO-INIT')->first();
        $project = Project::query()->where('code', 'PRJ-INIT')->first();
        $product = Product::query()->where('sku', 'PRC-0001')->first();
        $material = Product::query()->where('sku', 'MTL-0001')->first();
        $supplier = Supplier::query()->where('code', 'SUP-UMUM')->first();
        $admin = User::query()->where('email', 'admin@example.com')->first();

        if ($salesOrder !== null && $product !== null) {
            $invoice = Invoice::query()->updateOrCreate(
                ['invoice_number' => 'INV-INIT'],
                [
                    'sales_order_id' => $salesOrder->id,
                    'project_id' => $project?->id,
                    'customer_id' => $salesOrder->customer_id,
                    'invoice_date' => now()->toDateString(),
                    'due_date' => now()->addDays(14)->toDateString(),
                    'subtotal' => 1000000,
                    'tax_amount' => 0,
                    'total' => 1000000,
                    'paid_amount' => 0,
                    'status' => 'unpaid',
                ],
            );

            $invoice->items()->updateOrCreate(
                ['product_id' => $product->id],
                [
                    'description' => $product->name,
                    'quantity' => 1,
                    'unit_price' => 1000000,
                    'subtotal' => 1000000,
                ],
            );

            $invoice->payments()->updateOrCreate(
                ['payment_number' => 'PAY-INIT'],
                [
                    'payment_date' => now(),
                    'method' => 'transfer',
                    'amount' => 0,
                    'status' => 'pending',
                    'verified_by' => $admin?->id,
                    'verified_at' => null,
                    'notes' => 'Initial pending payment baseline.',
                ],
            );

            if ($project !== null) {
                $project->termins()->updateOrCreate(
                    ['phase' => 'Termin 1'],
                    [
                        'amount' => 1000000,
                        'due_date' => now()->addDays(14)->toDateString(),
                        'status' => 'unpaid',
                        'invoice_id' => $invoice->id,
                        'paid_at' => null,
                    ],
                );
            }
        }

        if ($supplier === null || $material === null) {
            return;
        }

        $purchaseOrder = PurchaseOrder::query()->updateOrCreate(
            ['po_number' => 'PO-INIT'],
            [
                'supplier_id' => $supplier->id,
                'po_date' => now()->toDateString(),
                'total' => 500000,
                'status' => 'draft',
                'notes' => 'Initial purchase order baseline.',
            ],
        );

        $purchaseOrder->items()->updateOrCreate(
            ['product_id' => $material->id],
            [
                'description' => $material->name,
                'quantity' => 1,
                'unit_price' => 500000,
                'received_qty' => 0,
                'subtotal' => 500000,
            ],
        );

        $purchaseOrder->supplierPayables()->updateOrCreate(
            ['payable_number' => 'PAYABLE-INIT'],
            [
                'supplier_id' => $supplier->id,
                'due_date' => now()->addDays(14)->toDateString(),
                'amount' => 500000,
                'paid_amount' => 0,
                'status' => 'open',
            ],
        );
    }
}
