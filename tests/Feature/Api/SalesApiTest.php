<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Quotation;
use App\Models\StockMovement;
use App\Models\StorageLocation;
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

    public function test_quotation_can_be_approved_into_sales_order_with_items(): void
    {
        $this->seed();

        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();
        $product = Product::query()->where('sku', 'PRC-0001')->firstOrFail();

        $quotationId = $this->postJson('/api/sales/quotations', [
            'quotation_number' => 'QUO-API-APPROVE',
            'customer_id' => $customer->id,
            'quotation_date' => '2026-06-05',
            'subtotal' => 300000,
            'tax_amount' => 0,
            'total' => 300000,
            'status' => 'sent',
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/sales/quotation-items', [
            'quotation_id' => $quotationId,
            'product_id' => $product->id,
            'description' => 'Approved quotation item',
            'quantity' => 3,
            'unit_price' => 100000,
            'subtotal' => 300000,
        ])->assertCreated();

        $response = $this->postJson("/api/sales/quotations/{$quotationId}/approve", [
            'order_number' => 'SO-API-APPROVED',
            'order_date' => '2026-06-06',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.order_number', 'SO-API-APPROVED')
            ->assertJsonPath('data.quotation.quotation_number', 'QUO-API-APPROVE')
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.items.0.product.sku', 'PRC-0001');

        $this->assertDatabaseHas('quotations', [
            'id' => $quotationId,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('sales_order_items', [
            'quantity' => 3,
            'subtotal' => 300000,
        ]);
    }

    public function test_sales_order_can_be_converted_into_delivery_order_with_items(): void
    {
        $this->seed();

        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();
        $product = Product::query()->where('sku', 'PRC-0001')->firstOrFail();

        $orderId = $this->postJson('/api/sales/sales-orders', [
            'order_number' => 'SO-API-DELIVER',
            'customer_id' => $customer->id,
            'order_date' => '2026-06-06',
            'total' => 450000,
            'status' => 'processing',
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/sales/sales-order-items', [
            'sales_order_id' => $orderId,
            'product_id' => $product->id,
            'description' => 'Delivery workflow item',
            'quantity' => 3,
            'unit_price' => 150000,
            'subtotal' => 450000,
        ])->assertCreated();

        $response = $this->postJson("/api/sales/sales-orders/{$orderId}/deliver", [
            'delivery_number' => 'DO-API-WORKFLOW',
            'delivery_date' => '2026-06-07',
            'receiver_name' => 'Penerima Workflow',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.delivery_number', 'DO-API-WORKFLOW')
            ->assertJsonPath('data.sales_order.order_number', 'SO-API-DELIVER')
            ->assertJsonPath('data.customer.code', 'CUST-UMUM')
            ->assertJsonPath('data.status', 'ready_to_load')
            ->assertJsonPath('data.items.0.product.sku', 'PRC-0001');

        $this->assertDatabaseHas('delivery_order_items', [
            'product_id' => $product->id,
            'quantity' => 3,
        ]);
    }

    public function test_delivery_order_can_be_shipped_into_stock_movements(): void
    {
        $this->seed();

        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();
        $product = Product::query()->where('sku', 'PRC-0001')->firstOrFail();
        $location = StorageLocation::query()->where('code', 'DEFAULT')->firstOrFail();
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        ProductStock::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'location_id' => $location->id,
            ],
            ['quantity' => 5],
        );

        $orderId = $this->postJson('/api/sales/sales-orders', [
            'order_number' => 'SO-API-SHIP',
            'customer_id' => $customer->id,
            'order_date' => '2026-06-06',
            'total' => 200000,
            'status' => 'processing',
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/sales/sales-order-items', [
            'sales_order_id' => $orderId,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100000,
            'subtotal' => 200000,
        ])->assertCreated();

        $deliveryId = $this->postJson("/api/sales/sales-orders/{$orderId}/deliver", [
            'delivery_number' => 'DO-API-SHIP',
            'delivery_date' => '2026-06-07',
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/sales/delivery-orders/{$deliveryId}/ship", [
            'from_location_id' => $location->id,
            'handled_by' => $admin->id,
            'movement_at' => '2026-06-07 09:00:00',
            'notes' => 'Shipment via API workflow.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'shipped');

        $stock = ProductStock::query()
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->firstOrFail();

        $this->assertSame('3.00', $stock->quantity);

        $movement = StockMovement::query()
            ->where('reference_number', 'DO-API-SHIP')
            ->firstOrFail();

        $this->assertSame('out', $movement->type);
        $this->assertSame('2.00', $movement->quantity);
        $this->assertSame($admin->id, $movement->handled_by);
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

    public function test_create_quotation_with_items_calculates_total_and_formats_dates(): void
    {
        $this->seed();

        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();
        $product = Product::query()->where('sku', 'PRC-0001')->firstOrFail();

        $response = $this->postJson('/api/sales/quotations', [
            'quotation_number' => 'QUO-WITH-ITEMS-1',
            'customer_id' => $customer->id,
            'quotation_date' => '2026-06-05',
            'valid_until' => '2026-06-19',
            'tax_amount' => 0,
            'status' => 'draft',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 150000,
                    'description' => 'Test Item',
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '300000.00')
            ->assertJsonPath('data.total', '300000.00')
            ->assertJsonPath('data.quotation_date', '2026-06-05')
            ->assertJsonPath('data.valid_until', '2026-06-19')
            ->assertJsonCount(1, 'data.items');

        $this->assertDatabaseHas('quotation_items', [
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 150000,
            'subtotal' => 300000,
        ]);
    }

    public function test_create_sales_order_with_items_calculates_total_and_formats_dates(): void
    {
        $this->seed();

        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();
        $product = Product::query()->where('sku', 'PRC-0001')->firstOrFail();

        $response = $this->postJson('/api/sales/sales-orders', [
            'order_number' => 'SO-WITH-ITEMS-1',
            'customer_id' => $customer->id,
            'order_date' => '2026-06-06',
            'status' => 'draft',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 3,
                    'unit_price' => 120000,
                    'description' => 'SO Test Item',
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.total', '360000.00')
            ->assertJsonPath('data.order_date', '2026-06-06')
            ->assertJsonCount(1, 'data.items');

        $this->assertDatabaseHas('sales_order_items', [
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 120000,
            'subtotal' => 360000,
        ]);
    }

    public function test_create_sales_order_clones_quotation_items_and_approves_quotation(): void
    {
        $this->seed();

        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();
        $product = Product::query()->where('sku', 'PRC-0001')->firstOrFail();

        $quotation = Quotation::query()->create([
            'quotation_number' => 'QUO-REF-123',
            'customer_id' => $customer->id,
            'quotation_date' => '2026-06-05',
            'subtotal' => 400000,
            'tax_amount' => 0,
            'total' => 400000,
            'status' => 'sent',
        ]);

        $quotation->items()->create([
            'product_id' => $product->id,
            'description' => 'Reference Item',
            'quantity' => 4,
            'unit_price' => 100000,
            'subtotal' => 400000,
        ]);

        $response = $this->postJson('/api/sales/sales-orders', [
            'quotation_id' => $quotation->id,
            'order_number' => 'SO-REF-123',
            'customer_id' => $customer->id,
            'order_date' => '2026-06-06',
            'status' => 'draft',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.total', '400000.00')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.product.sku', 'PRC-0001');

        $this->assertDatabaseHas('quotations', [
            'id' => $quotation->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('sales_order_items', [
            'product_id' => $product->id,
            'quantity' => 4,
            'unit_price' => 100000,
            'subtotal' => 400000,
        ]);
    }
}
