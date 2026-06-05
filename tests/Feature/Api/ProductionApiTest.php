<?php

namespace Tests\Feature\Api;

use App\Models\Employee;
use App\Models\Product;
use App\Models\Project;
use App\Models\SalesOrder;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_order_item_and_log_can_be_created(): void
    {
        $this->seed();

        $finishedProduct = Product::query()->where('sku', 'PRC-0001')->firstOrFail();
        $materialProduct = Product::query()->where('sku', 'MTL-0001')->firstOrFail();
        $salesOrder = SalesOrder::query()->where('order_number', 'SO-INIT')->firstOrFail();
        $project = Project::query()->where('code', 'PRJ-INIT')->firstOrFail();
        $employee = Employee::query()->where('employee_number', 'EMP-PRD-INIT')->firstOrFail();
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $workOrderResponse = $this->postJson('/api/production/work-orders', [
            'work_order_number' => 'WO-API-001',
            'product_id' => $finishedProduct->id,
            'sales_order_id' => $salesOrder->id,
            'project_id' => $project->id,
            'source_label' => 'SO-INIT',
            'stage' => 'draft',
            'target_qty' => 5,
            'completed_qty' => 0,
            'progress' => 0,
            'due_date' => '2026-06-20',
        ]);

        $workOrderResponse
            ->assertCreated()
            ->assertJsonPath('data.work_order_number', 'WO-API-001')
            ->assertJsonPath('data.product.sku', 'PRC-0001')
            ->assertJsonPath('data.project.code', 'PRJ-INIT');

        $workOrderId = $workOrderResponse->json('data.id');

        $this->postJson('/api/production/work-order-items', [
            'work_order_id' => $workOrderId,
            'product_id' => $materialProduct->id,
            'quantity' => 5,
            'notes' => 'API material requirement.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.work_order.work_order_number', 'WO-API-001')
            ->assertJsonPath('data.product.sku', 'MTL-0001');

        $this->postJson('/api/production/work-logs', [
            'work_order_id' => $workOrderId,
            'employee_id' => $employee->id,
            'work_date' => '2026-06-05',
            'stage' => 'cetak',
            'made_qty' => 2,
            'reject_qty' => 0,
            'ok_qty' => 2,
            'piece_rate' => 10000,
            'verified_by' => $admin->id,
            'verified_at' => '2026-06-05 12:00:00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.work_order.work_order_number', 'WO-API-001')
            ->assertJsonPath('data.employee.employee_number', 'EMP-PRD-INIT')
            ->assertJsonPath('data.verified_by.email', 'admin@example.com');

        $this->getJson('/api/production/work-orders?q=WO-API')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_bom_and_bom_item_can_be_created(): void
    {
        $this->seed();

        $finishedProduct = Product::query()->where('sku', 'PRC-0001')->firstOrFail();
        $materialProduct = Product::query()->where('sku', 'MTL-0001')->firstOrFail();
        $kgUnit = Unit::query()->where('code', 'KG')->firstOrFail();

        $bomResponse = $this->postJson('/api/production/boms', [
            'product_id' => $finishedProduct->id,
            'version' => 'v-api',
            'effective_from' => '2026-06-05',
            'status' => 'active',
            'total_cost' => 750000,
        ]);

        $bomResponse
            ->assertCreated()
            ->assertJsonPath('data.version', 'v-api')
            ->assertJsonPath('data.product.sku', 'PRC-0001');

        $bomId = $bomResponse->json('data.id');

        $this->postJson('/api/production/bom-items', [
            'bom_id' => $bomId,
            'component_product_id' => $materialProduct->id,
            'component_name' => 'API Material',
            'quantity' => 3,
            'unit_id' => $kgUnit->id,
            'unit_cost' => 250000,
            'subtotal' => 750000,
        ])
            ->assertCreated()
            ->assertJsonPath('data.bom.version', 'v-api')
            ->assertJsonPath('data.component_product.sku', 'MTL-0001')
            ->assertJsonPath('data.unit.code', 'KG');
    }

    public function test_production_api_rejects_invalid_progress(): void
    {
        $this->seed();

        $product = Product::query()->where('sku', 'PRC-0001')->firstOrFail();

        $this->postJson('/api/production/work-orders', [
            'work_order_number' => 'WO-API-BAD',
            'product_id' => $product->id,
            'stage' => 'draft',
            'target_qty' => 1,
            'progress' => 120,
        ])->assertUnprocessable();
    }
}
