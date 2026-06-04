<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Database\Seeder;

class ErpSalesSeeder extends Seeder
{
    /**
     * Seed a minimal sales document chain for development and relationship tests.
     */
    public function run(): void
    {
        $customer = Customer::query()->updateOrCreate(
            ['code' => 'CUST-UMUM'],
            [
                'name' => 'Customer Umum',
                'phone' => null,
                'email' => null,
                'city' => null,
                'address' => null,
                'status' => 'active',
            ],
        );

        $admin = User::query()->where('email', 'admin@example.com')->first();
        $product = Product::query()->where('sku', 'PRC-0001')->first();

        if ($product === null) {
            return;
        }

        $quotation = Quotation::query()->updateOrCreate(
            ['quotation_number' => 'QUO-INIT'],
            [
                'customer_id' => $customer->id,
                'created_by' => $admin?->id,
                'quotation_date' => now()->toDateString(),
                'valid_until' => now()->addDays(14)->toDateString(),
                'subtotal' => 1000000,
                'tax_amount' => 0,
                'total' => 1000000,
                'status' => 'draft',
                'notes' => 'Initial quotation baseline.',
            ],
        );

        $quotation->items()->updateOrCreate(
            ['product_id' => $product->id],
            [
                'description' => $product->name,
                'quantity' => 1,
                'unit_price' => 1000000,
                'subtotal' => 1000000,
            ],
        );

        $salesOrder = SalesOrder::query()->updateOrCreate(
            ['order_number' => 'SO-INIT'],
            [
                'quotation_id' => $quotation->id,
                'customer_id' => $customer->id,
                'order_date' => now()->toDateString(),
                'total' => 1000000,
                'status' => 'draft',
                'notes' => 'Initial sales order baseline.',
            ],
        );

        $salesOrderItem = $salesOrder->items()->updateOrCreate(
            ['product_id' => $product->id],
            [
                'description' => $product->name,
                'quantity' => 1,
                'unit_price' => 1000000,
                'subtotal' => 1000000,
            ],
        );

        $deliveryOrder = DeliveryOrder::query()->updateOrCreate(
            ['delivery_number' => 'DO-INIT'],
            [
                'sales_order_id' => $salesOrder->id,
                'customer_id' => $customer->id,
                'delivery_date' => now()->toDateString(),
                'received_at' => null,
                'receiver_name' => null,
                'status' => 'ready_to_load',
                'notes' => 'Initial delivery order baseline.',
            ],
        );

        $deliveryOrder->items()->updateOrCreate(
            ['sales_order_item_id' => $salesOrderItem->id],
            [
                'product_id' => $product->id,
                'quantity' => 1,
            ],
        );
    }
}
