<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Quotation;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpSalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_seed_creates_document_chain(): void
    {
        $this->seed();

        $this->assertDatabaseHas('customers', ['code' => 'CUST-UMUM']);
        $this->assertDatabaseHas('quotations', ['quotation_number' => 'QUO-INIT']);
        $this->assertDatabaseHas('sales_orders', ['order_number' => 'SO-INIT']);
        $this->assertDatabaseHas('delivery_orders', ['delivery_number' => 'DO-INIT']);
    }

    public function test_quotation_relations_are_available(): void
    {
        $this->seed();

        $quotation = Quotation::query()->where('quotation_number', 'QUO-INIT')->firstOrFail();

        $this->assertSame('CUST-UMUM', $quotation->customer->code);
        $this->assertSame('admin@example.com', $quotation->createdBy?->email);
        $this->assertSame('PRC-0001', $quotation->items->first()?->product?->sku);
        $this->assertSame('1000000.00', $quotation->total);
    }

    public function test_sales_order_relations_are_available(): void
    {
        $this->seed();

        $salesOrder = SalesOrder::query()->where('order_number', 'SO-INIT')->firstOrFail();

        $this->assertSame('QUO-INIT', $salesOrder->quotation?->quotation_number);
        $this->assertSame('CUST-UMUM', $salesOrder->customer->code);
        $this->assertSame('PRC-0001', $salesOrder->items->first()?->product?->sku);
        $this->assertSame('DO-INIT', $salesOrder->deliveryOrders->first()?->delivery_number);
    }

    public function test_delivery_order_relations_are_available(): void
    {
        $this->seed();

        $deliveryOrder = DeliveryOrder::query()->where('delivery_number', 'DO-INIT')->firstOrFail();
        $deliveryItem = DeliveryOrderItem::query()->firstOrFail();

        $this->assertSame('SO-INIT', $deliveryOrder->salesOrder->order_number);
        $this->assertSame('CUST-UMUM', $deliveryOrder->customer->code);
        $this->assertSame('PRC-0001', $deliveryItem->product->sku);
        $this->assertSame('1.00', $deliveryItem->quantity);
    }

    public function test_customer_has_sales_document_relations(): void
    {
        $this->seed();

        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();

        $this->assertTrue($customer->quotations->contains('quotation_number', 'QUO-INIT'));
        $this->assertTrue($customer->salesOrders->contains('order_number', 'SO-INIT'));
        $this->assertTrue($customer->deliveryOrders->contains('delivery_number', 'DO-INIT'));
    }
}
