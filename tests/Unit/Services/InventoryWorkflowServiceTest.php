<?php

namespace Tests\Unit\Services;

use App\Models\Bag;
use App\Models\Product;
use App\Models\StorageLocation;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryWorkflowService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryWorkflowService $service;

    protected Product $product;

    protected Warehouse $warehouse;

    protected StorageLocation $location;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Model::unguard();

        $this->service = app(InventoryWorkflowService::class);

        $this->admin = User::forceCreate([
            'name' => 'Admin Test',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $this->product = Product::forceCreate([
            'sku' => 'PRC-0001',
            'name' => 'Produk Test',
            'cost_price' => 20000,
        ]);

        $this->warehouse = Warehouse::forceCreate([
            'code' => 'WH-01',
            'name' => 'Main Warehouse',
        ]);

        $this->location = StorageLocation::forceCreate([
            'warehouse_id' => $this->warehouse->id,
            'code' => 'LOC-01',
            'name' => 'Gudang Utama',
        ]);

        DB::table('product_stocks')->insert([
            'product_id' => $this->product->id,
            'location_id' => $this->location->id,
            'quantity' => 100,
            'updated_at' => now(),
        ]);
    }

    public function test_process_bag_in_increases_stock()
    {
        $attributes = [
            'date' => date('Y-m-d'),
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->location->id,
            'type' => 'in',
            'created_by' => $this->admin->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 50,
                ],
            ],
        ];

        $bag = $this->service->processBag($attributes);

        $this->assertInstanceOf(Bag::class, $bag);
        $this->assertEquals('in', $bag->type);

        $stock = DB::table('product_stocks')
            ->where('product_id', $this->product->id)
            ->where('location_id', $this->location->id)
            ->first();

        $this->assertEquals(150, $stock->quantity); // 100 + 50

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'to_location_id' => $this->location->id,
            'type' => 'in',
            'quantity' => 50,
            'reference_type' => 'bag',
            'reference_id' => $bag->id,
        ]);
    }

    public function test_process_bag_out_decreases_stock()
    {
        $attributes = [
            'date' => date('Y-m-d'),
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->location->id,
            'type' => 'out',
            'created_by' => $this->admin->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 20,
                ],
            ],
        ];

        $bag = $this->service->processBag($attributes);

        $stock = DB::table('product_stocks')
            ->where('product_id', $this->product->id)
            ->where('location_id', $this->location->id)
            ->first();

        $this->assertEquals(80, $stock->quantity); // 100 - 20

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'from_location_id' => $this->location->id,
            'type' => 'out',
            'quantity' => 20,
            'reference_type' => 'bag',
            'reference_id' => $bag->id,
        ]);
    }
}
