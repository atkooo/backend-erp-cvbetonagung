<?php

namespace Tests\Unit\Services;

use App\Models\Bom;
use App\Models\BomItem;
use App\Models\Product;
use App\Models\ProductionWorkOrder;
use App\Models\StorageLocation;
use App\Models\Warehouse;
use App\Services\ProductionWorkflowService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ProductionWorkflowService $service;

    protected Product $finishedProduct;

    protected Product $rawMaterial;

    protected Warehouse $warehouse;

    protected StorageLocation $location;

    protected Bom $bom;

    protected function setUp(): void
    {
        parent::setUp();

        Model::unguard();

        $this->service = app(ProductionWorkflowService::class);

        $this->finishedProduct = Product::forceCreate([
            'sku' => 'FG-0001',
            'name' => 'Barang Jadi Test',
            'cost_price' => 50000,
        ]);

        $this->rawMaterial = Product::forceCreate([
            'sku' => 'RM-0001',
            'name' => 'Bahan Baku Test',
            'cost_price' => 10000,
        ]);

        $this->warehouse = Warehouse::forceCreate([
            'code' => 'WH-PROD',
            'name' => 'Gudang Produksi',
        ]);

        $this->location = StorageLocation::forceCreate([
            'warehouse_id' => $this->warehouse->id,
            'code' => 'LOC-PROD',
            'name' => 'Lokasi Produksi',
        ]);

        $this->bom = Bom::forceCreate([
            'product_id' => $this->finishedProduct->id,
            'version' => '1.0',
            'status' => 'active',
        ]);

        BomItem::forceCreate([
            'bom_id' => $this->bom->id,
            'component_product_id' => $this->rawMaterial->id,
            'quantity' => 2, // 2 unit RM per 1 unit FG
            'unit_cost' => 10000,
            'subtotal' => 20000,
        ]);

        DB::table('product_stocks')->insert([
            'product_id' => $this->rawMaterial->id,
            'location_id' => $this->location->id,
            'quantity' => 1000,
            'updated_at' => now(),
        ]);

        DB::table('product_stocks')->insert([
            'product_id' => $this->finishedProduct->id,
            'location_id' => $this->location->id,
            'quantity' => 0,
            'updated_at' => now(),
        ]);
    }

    public function test_receive_work_order_updates_progress_increases_fg_and_deducts_rm()
    {
        $wo = ProductionWorkOrder::forceCreate([
            'work_order_number' => 'WO-001',
            'product_id' => $this->finishedProduct->id,
            'stage' => 'assembly',
            'target_qty' => 100,
            'completed_qty' => 0,
            'progress' => 0,
        ]);

        $receivedWo = $this->service->receiveWorkOrder($wo->id, [
            'quantity' => 25,
            'target_location_id' => $this->location->id,
            'source_location_id' => $this->location->id,
            'movement_at' => now()->toDateTimeString(),
        ]);

        $this->assertEquals(25, $receivedWo->completed_qty);
        $this->assertEquals(25, $receivedWo->progress); // 25 / 100 * 100

        $fgStock = DB::table('product_stocks')
            ->where('product_id', $this->finishedProduct->id)
            ->where('location_id', $this->location->id)
            ->first();
        $this->assertEquals(25, $fgStock->quantity); // 0 + 25

        $rmStock = DB::table('product_stocks')
            ->where('product_id', $this->rawMaterial->id)
            ->where('location_id', $this->location->id)
            ->first();
        $this->assertEquals(950, $rmStock->quantity); // 1000 - (2 * 25)

        // Verify FG movement
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->finishedProduct->id,
            'to_location_id' => $this->location->id,
            'type' => 'in',
            'quantity' => 25,
            'reference_type' => 'production_work_order',
            'reference_id' => $wo->id,
        ]);

        // Verify RM movement
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->rawMaterial->id,
            'from_location_id' => $this->location->id,
            'type' => 'out',
            'quantity' => 50,
            'reference_type' => 'production_work_order',
            'reference_id' => $wo->id,
        ]);
    }
}
