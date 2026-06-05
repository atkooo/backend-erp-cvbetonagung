<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_quotation_and_item_can_be_created_listed_and_updated(): void
    {
        $this->seed();

        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();
        $product = Product::query()->where('sku', 'PRC-0001')->firstOrFail();
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $quotationResponse = $this->postJson('/api/sales/quotations', [
            'quotation_number' => 'QUO-API-001',
            'customer_id' => $customer->id,
            'created_by' => $admin->id,
            'quotation_date' => '2026-06-05',
            'valid_until' => '2026-07-05',
            'subtotal' => 100000,
            'tax_amount' => 11000,
            'total' => 111000,
            'status' => 'draft',
        ]);

        $quotationResponse
            ->assertCreated()
            ->assertJsonPath('data.quotation_number', 'QUO-API-001')
            ->assertJsonPath('data.customer.code', 'CUST-UMUM');

        $quotationId = $quotationResponse->json('data.id');

        $this->postJson('/api/sales/quotation-items', [
            'quotation_id' => $quotationId,
            'product_id' => $product->id,
            'description' => 'API quotation item',
            'quantity' => 1,
            'unit_price' => 100000,
            'subtotal' => 100000,
        ])
            ->assertCreated()
            ->assertJsonPath('data.quotation.quotation_number', 'QUO-API-001')
            ->assertJsonPath('data.product.sku', 'PRC-0001');

        $this->getJson('/api/sales/quotations?q=QUO-API')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->patchJson("/api/sales/quotations/{$quotationId}", [
            'status' => 'sent',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'sent');
    }

    public function test_sales_order_and_item_can_be_created_from_quotation(): void
    {
        $this->seed();

        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();
        $product = Product::query()->where('sku', 'PRC-0001')->firstOrFail();

        $quotationId = $this->postJson('/api/sales/quotations', [
            'quotation_number' => 'QUO-API-002',
            'customer_id' => $customer->id,
            'quotation_date' => '2026-06-05',
            'total' => 250000,
            'status' => 'approved',
        ])->assertCreated()->json('data.id');

        $orderResponse = $this->postJson('/api/sales/sales-orders', [
            'quotation_id' => $quotationId,
            'order_number' => 'SO-API-001',
            'customer_id' => $customer->id,
            'order_date' => '2026-06-06',
            'total' => 250000,
            'status' => 'processing',
        ]);

        $orderResponse
            ->assertCreated()
            ->assertJsonPath('data.order_number', 'SO-API-001')
            ->assertJsonPath('data.quotation.quotation_number', 'QUO-API-002');

        $orderId = $orderResponse->json('data.id');

        $this->postJson('/api/sales/sales-order-items', [
            'sales_order_id' => $orderId,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 125000,
            'subtotal' => 250000,
        ])
            ->assertCreated()
            ->assertJsonPath('data.sales_order.order_number', 'SO-API-001')
            ->assertJsonPath('data.product.sku', 'PRC-0001');
    }

    public function test_delivery_order_and_item_can_be_created_from_sales_order(): void
    {
        $this->seed();

        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();
        $product = Product::query()->where('sku', 'PRC-0001')->firstOrFail();

        $orderId = $this->postJson('/api/sales/sales-orders', [
            'order_number' => 'SO-API-002',
            'customer_id' => $customer->id,
            'order_date' => '2026-06-06',
            'total' => 150000,
            'status' => 'processing',
        ])->assertCreated()->json('data.id');

        $orderItemId = $this->postJson('/api/sales/sales-order-items', [
            'sales_order_id' => $orderId,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 150000,
            'subtotal' => 150000,
        ])->assertCreated()->json('data.id');

        $deliveryResponse = $this->postJson('/api/sales/delivery-orders', [
            'delivery_number' => 'DO-API-001',
            'sales_order_id' => $orderId,
            'customer_id' => $customer->id,
            'delivery_date' => '2026-06-07',
            'receiver_name' => 'Penerima API',
            'status' => 'ready_to_load',
        ]);

        $deliveryResponse
            ->assertCreated()
            ->assertJsonPath('data.delivery_number', 'DO-API-001')
            ->assertJsonPath('data.sales_order.order_number', 'SO-API-002');

        $deliveryId = $deliveryResponse->json('data.id');

        $this->postJson('/api/sales/delivery-order-items', [
            'delivery_order_id' => $deliveryId,
            'sales_order_item_id' => $orderItemId,
            'product_id' => $product->id,
            'quantity' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('data.delivery_order.delivery_number', 'DO-API-001')
            ->assertJsonPath('data.product.sku', 'PRC-0001');
    }

    public function test_sales_api_rejects_invalid_status(): void
    {
        $this->seed();

        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();

        $this->postJson('/api/sales/quotations', [
            'quotation_number' => 'QUO-API-BAD',
            'customer_id' => $customer->id,
            'quotation_date' => '2026-06-05',
            'status' => 'processing',
        ])->assertUnprocessable();
    }
}
