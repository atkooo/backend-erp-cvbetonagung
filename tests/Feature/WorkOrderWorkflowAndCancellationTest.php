<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductionWorkLog;
use App\Models\ProductionWorkOrder;
use App\Models\SalesOrder;
use App\Models\Unit;
use App\Models\User;
use App\Services\CancellationService;
use App\Services\StockProductionRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WorkOrderWorkflowAndCancellationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    private Unit $unit;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->customer = Customer::create([
            'name' => 'PT Beton Perkasa',
            'phone' => '081234567890',
        ]);
        $this->unit = Unit::create([
            'name' => 'Pieces',
            'code' => 'PCS',
        ]);
        $this->product = Product::create([
            'sku' => 'PRD-U40',
            'name' => 'U-Ditch 40x40x120',
            'type' => 'finished_good',
            'unit_id' => $this->unit->id,
            'cost_price' => 150000,
            'selling_price' => 220000,
            'min_stock' => 10,
        ]);
    }

    public function test_sales_order_cancellation_auto_cancels_draft_work_orders(): void
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'order_date' => date('Y-m-d'),
            'total' => 2200000,
            'status' => 'approved',
        ]);

        $wo = ProductionWorkOrder::create([
            'product_id' => $this->product->id,
            'sales_order_id' => $so->id,
            'source_label' => 'SO Test',
            'stage' => 'Draft',
            'target_qty' => 10,
            'completed_qty' => 0,
            'progress' => 0,
        ]);

        $cancellationService = app(CancellationService::class);
        $cancelledSo = $cancellationService->cancelSalesOrder($so->id, $this->user->id, 'Pelanggan minta batal');

        $this->assertEquals('cancelled', $cancelledSo->status);

        $wo->refresh();
        $this->assertEquals('cancelled', $wo->stage);
    }

    public function test_sales_order_cancellation_is_rejected_if_work_order_is_in_progress(): void
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'order_date' => date('Y-m-d'),
            'total' => 2200000,
            'status' => 'approved',
        ]);

        $wo = ProductionWorkOrder::create([
            'product_id' => $this->product->id,
            'sales_order_id' => $so->id,
            'source_label' => 'SO Test',
            'stage' => 'Cetak & Curing',
            'target_qty' => 10,
            'completed_qty' => 0,
            'progress' => 0,
        ]);

        // Tambah log pengerjaan di lantai produksi
        ProductionWorkLog::create([
            'work_order_id' => $wo->id,
            'work_date' => date('Y-m-d'),
            'stage' => 'Cetak & Curing',
            'made_qty' => 5,
            'reject_qty' => 0,
            'ok_qty' => 5,
        ]);

        $cancellationService = app(CancellationService::class);

        $this->expectException(HttpException::class);
        $cancellationService->cancelSalesOrder($so->id, $this->user->id, 'Batal SO saat proses produksi');
    }

    public function test_stock_production_request_approves_and_generates_work_orders(): void
    {
        $sprService = app(StockProductionRequestService::class);

        $spr = $sprService->createRequest([
            'notes' => 'Restok untuk persediaan proyek musim hujan',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'target_qty' => 50,
                    'notes' => 'Prioritas',
                ],
            ],
        ], $this->user->id);

        $this->assertEquals('draft', $spr->status);
        $this->assertCount(1, $spr->items);

        $result = $sprService->approveRequest($spr->id, $this->user->id);

        $spr->refresh();
        $this->assertEquals('approved', $spr->status);
        $this->assertCount(1, $result['work_orders']);

        $wo = $result['work_orders'][0];
        $this->assertEquals($this->product->id, $wo->product_id);
        $this->assertEquals($spr->id, $wo->stock_production_request_id);
        $this->assertEquals(50, (float) $wo->target_qty);
        $this->assertEquals('Draft', $wo->stage);
        $this->assertStringContainsString($spr->request_number, $wo->source_label);
    }

    public function test_stock_production_request_cancellation_auto_cancels_draft_work_orders(): void
    {
        $sprService = app(StockProductionRequestService::class);
        $cancellationService = app(CancellationService::class);

        $spr = $sprService->createRequest([
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'target_qty' => 20,
                ],
            ],
        ], $this->user->id);

        $sprService->approveRequest($spr->id, $this->user->id);

        $wos = ProductionWorkOrder::where('stock_production_request_id', $spr->id)->get();
        $this->assertCount(1, $wos);
        $this->assertEquals('Draft', $wos[0]->stage);

        // Batalkan SPR
        $cancelledSpr = $cancellationService->cancelStockProductionRequest($spr->id, $this->user->id, 'Salah kalkulasi stok');

        $this->assertEquals('cancelled', $cancelledSpr->status);

        $wos[0]->refresh();
        $this->assertEquals('cancelled', $wos[0]->stage);
    }
}
