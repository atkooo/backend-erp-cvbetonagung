<?php

namespace Tests\Feature;

use App\Models\Bom;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductionWorkLog;
use App\Models\ProductionWorkOrder;
use App\Models\Project;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpProductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_seed_creates_work_order_log_and_bom(): void
    {
        $this->seed();

        $this->assertDatabaseHas('production_work_orders', ['work_order_number' => 'WO-INIT']);
        $this->assertDatabaseHas('production_work_order_items', ['quantity' => 1]);
        $this->assertDatabaseHas('production_work_logs', ['stage' => 'cetak']);
        $this->assertDatabaseHas('boms', ['version' => 'v1']);
        $this->assertDatabaseHas('bom_items', ['subtotal' => 500000]);
    }

    public function test_production_work_order_relations_are_available(): void
    {
        $this->seed();

        $workOrder = ProductionWorkOrder::query()->where('work_order_number', 'WO-INIT')->firstOrFail();

        $this->assertSame('PRC-0001', $workOrder->product->sku);
        $this->assertSame('SO-INIT', $workOrder->salesOrder?->order_number);
        $this->assertSame('PRJ-INIT', $workOrder->project?->code);
        $this->assertSame('MTL-0001', $workOrder->items->first()?->product?->sku);
        $this->assertSame('cetak', $workOrder->logs->first()?->stage);
    }

    public function test_production_log_relations_are_available(): void
    {
        $this->seed();

        $log = ProductionWorkLog::query()->where('stage', 'cetak')->firstOrFail();

        $this->assertSame('WO-INIT', $log->workOrder->work_order_number);
        $this->assertSame('EMP-PRD-INIT', $log->employee?->employee_number);
        $this->assertSame('admin@example.com', $log->verifiedBy?->email);
        $this->assertSame('1.00', $log->ok_qty);
    }

    public function test_bom_relations_are_available(): void
    {
        $this->seed();

        $bom = Bom::query()->where('version', 'v1')->firstOrFail();

        $this->assertSame('PRC-0001', $bom->product->sku);
        $this->assertSame('MTL-0001', $bom->items->first()?->componentProduct?->sku);
        $this->assertSame('KG', $bom->items->first()?->unit?->code);
        $this->assertSame('500000.00', $bom->total_cost);
    }

    public function test_parent_models_can_reach_production_records(): void
    {
        $this->seed();

        $product = Product::query()->where('sku', 'PRC-0001')->firstOrFail();
        $salesOrder = SalesOrder::query()->where('order_number', 'SO-INIT')->firstOrFail();
        $project = Project::query()->where('code', 'PRJ-INIT')->firstOrFail();
        $employee = Employee::query()->where('employee_number', 'EMP-PRD-INIT')->firstOrFail();

        $this->assertTrue($product->productionWorkOrders->contains('work_order_number', 'WO-INIT'));
        $this->assertTrue($product->boms->contains('version', 'v1'));
        $this->assertTrue($salesOrder->productionWorkOrders->contains('work_order_number', 'WO-INIT'));
        $this->assertTrue($project->productionWorkOrders->contains('work_order_number', 'WO-INIT'));
        $this->assertTrue($employee->productionWorkLogs->contains('stage', 'cetak'));
    }
}
