<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Database\Seeder;

class ErpReturnSeeder extends Seeder
{
    /**
     * Seed customer and supplier return examples.
     */
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->first();
        $salesOrder = SalesOrder::query()->where('order_number', 'SO-INIT')->first();
        $purchaseOrder = PurchaseOrder::query()->where('po_number', 'PO-INIT')->first();
        $salesProduct = Product::query()->where('sku', 'PRC-0001')->first();
        $materialProduct = Product::query()->where('sku', 'MTL-0001')->first();

        if ($salesOrder !== null && $salesProduct !== null) {
            $customerReturn = ProductReturn::query()->updateOrCreate(
                ['return_number' => 'RET-CUST-INIT'],
                [
                    'type' => 'customer',
                    'customer_id' => $salesOrder->customer_id,
                    'supplier_id' => null,
                    'sales_order_id' => $salesOrder->id,
                    'purchase_order_id' => null,
                    'reason' => 'Initial customer return baseline.',
                    'qc_status' => 'waiting_qc',
                    'created_by' => $admin?->id,
                ],
            );

            $customerReturn->items()->updateOrCreate(
                ['product_id' => $salesProduct->id],
                [
                    'quantity' => 1,
                    'notes' => 'Customer returned item baseline.',
                ],
            );
        }

        if ($purchaseOrder !== null && $materialProduct !== null) {
            $supplierReturn = ProductReturn::query()->updateOrCreate(
                ['return_number' => 'RET-SUP-INIT'],
                [
                    'type' => 'supplier',
                    'customer_id' => null,
                    'supplier_id' => $purchaseOrder->supplier_id,
                    'sales_order_id' => null,
                    'purchase_order_id' => $purchaseOrder->id,
                    'reason' => 'Initial supplier return baseline.',
                    'qc_status' => 'supplier_claim',
                    'created_by' => $admin?->id,
                ],
            );

            $supplierReturn->items()->updateOrCreate(
                ['product_id' => $materialProduct->id],
                [
                    'quantity' => 1,
                    'notes' => 'Supplier returned material baseline.',
                ],
            );
        }
    }
}
