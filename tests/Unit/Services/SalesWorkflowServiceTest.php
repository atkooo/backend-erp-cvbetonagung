<?php

namespace Tests\Unit\Services;

use App\Enums\DeliveryOrderStatus;
use App\Enums\SalesOrderStatus;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\StorageLocation;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SalesWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    private SalesWorkflowService $service;

    private Customer $customer;

    private Product $product;

    private User $admin;

    private StorageLocation $location;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SalesWorkflowService::class);

        $this->admin = User::forceCreate([
            'name' => 'Admin Test',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $this->customer = Customer::forceCreate([
            'code' => 'CUST-UMUM',
            'name' => 'Pelanggan Umum',
            'email' => 'umum@example.com',
            'phone' => '08123456789',
            'status' => 'active',
        ]);

        $this->product = Product::forceCreate([
            'sku' => 'PRC-0001',
            'name' => 'Produk Test',
            'cost_price' => 20000,
        ]);

        $warehouse = Warehouse::forceCreate([
            'code' => 'WH-01',
            'name' => 'Main Warehouse',
        ]);

        $this->location = StorageLocation::forceCreate([
            'warehouse_id' => $warehouse->id,
            'code' => 'LOC-01',
            'name' => 'Gudang Utama',
        ]);

        $this->account = Account::forceCreate([
            'code' => 'ACC-01',
            'name' => 'Kas Test',
            'type' => 'cash',
            'is_active' => true,
            'currency' => 'IDR',
        ]);
    }

    public function test_it_can_create_quotation()
    {
        $data = [
            'quotation_number' => 'QUO-TEST-001',
            'customer_id' => $this->customer->id,
            'created_by' => $this->admin->id,
            'quotation_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'status' => 'draft',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 50000,
                ],
            ],
        ];

        $quotation = $this->service->createQuotation($data);

        $this->assertInstanceOf(Quotation::class, $quotation);
        $this->assertDatabaseHas('quotations', [
            'quotation_number' => 'QUO-TEST-001',
            'customer_id' => $this->customer->id,
            'subtotal' => 100000,
        ]);
        $this->assertDatabaseHas('quotation_items', [
            'quotation_id' => $quotation->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => 50000,
            'subtotal' => 100000,
        ]);
    }

    public function test_it_can_approve_quotation_to_sales_order()
    {
        $quotationData = [
            'quotation_number' => 'QUO-TEST-002',
            'customer_id' => $this->customer->id,
            'created_by' => $this->admin->id,
            'quotation_date' => now()->toDateString(),
            'status' => 'draft',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                    'unit_price' => 50000,
                ],
            ],
        ];
        $quotation = $this->service->createQuotation($quotationData);

        $salesOrder = $this->service->approveQuotation($quotation->id, [
            'notes' => 'Approved via unit test',
        ]);

        $this->assertInstanceOf(SalesOrder::class, $salesOrder);

        $quotation->refresh();
        $this->assertEquals('approved', $quotation->status);

        $this->assertDatabaseHas('sales_orders', [
            'id' => $salesOrder->id,
            'quotation_id' => $quotation->id,
            'customer_id' => $this->customer->id,
            'status' => 'processing',
            'total' => 50000,
        ]);
    }

    public function test_process_pos_deducts_stock_and_creates_transaction()
    {
        DB::table('product_stocks')->insert([
            'product_id' => $this->product->id,
            'location_id' => $this->location->id,
            'quantity' => 100,
            'updated_at' => now(),
        ]);

        $initialStock = 100;

        $posData = [
            'customer_id' => $this->customer->id,
            'payment_account_id' => $this->account->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'location_id' => $this->location->id,
                    'quantity' => 5,
                    'unit_price' => 10000,
                ],
            ],
            'amount_paid' => 50000,
        ];

        $salesOrder = $this->service->processPOS($posData);

        $this->assertInstanceOf(SalesOrder::class, $salesOrder);
        $this->assertEquals(SalesOrderStatus::Completed->value, $salesOrder->status);

        $stock = ProductStock::where('product_id', $this->product->id)->where('location_id', $this->location->id)->first();
        $this->assertEquals($initialStock - 5, $stock->quantity);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'from_location_id' => $this->location->id,
            'quantity' => 5,
            'type' => 'out',
        ]);

        $this->assertDatabaseHas('cash_transactions', [
            'account_id' => $this->account->id,
            'amount' => 50000,
            'type' => 'in',
            'category' => 'sales',
        ]);
    }

    public function test_it_handles_insufficient_stock_by_creating_draft_delivery_order()
    {
        DB::table('product_stocks')->insert([
            'product_id' => $this->product->id,
            'location_id' => $this->location->id,
            'quantity' => 0,
            'updated_at' => now(),
        ]);

        $attributes = [
            'customer_id' => $this->customer->id,
            'payment_account_id' => $this->account->id,
            'transaction_date' => date('Y-m-d'),
            'amount_paid' => 100000,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'unit_price' => 10000,
                    'fulfillment_type' => 'take_away',
                    'location_id' => $this->location->id,
                ],
            ],
        ];

        $salesOrder = $this->service->processPOS($attributes);

        $this->assertEquals(SalesOrderStatus::PendingDelivery->value, $salesOrder->status);
        $this->assertDatabaseHas('delivery_orders', [
            'sales_order_id' => $salesOrder->id,
            'status' => DeliveryOrderStatus::Draft->value,
        ]);
    }
}
