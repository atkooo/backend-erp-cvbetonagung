<?php

namespace Database\Seeders;

use App\Models\Bom;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductionWorkOrder;
use App\Models\Project;
use App\Models\SalesOrder;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

class ErpProductionSeeder extends Seeder
{
    /**
     * Seed baseline production work order and BOM records.
     */
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->first();
        $finishedProduct = Product::query()->where('sku', 'PRC-0001')->first();
        $materialProduct = Product::query()->where('sku', 'MTL-0001')->first();
        $salesOrder = SalesOrder::query()->where('order_number', 'SO-INIT')->first();
        $project = Project::query()->where('code', 'PRJ-INIT')->first();
        $kgUnit = Unit::query()->where('code', 'KG')->first();

        if ($finishedProduct === null || $materialProduct === null) {
            return;
        }

        $employee = Employee::query()->updateOrCreate(
            ['employee_number' => 'EMP-PRD-INIT'],
            [
                'user_id' => null,
                'name' => 'Operator Produksi Awal',
                'role_name' => 'operator',
                'department' => 'workshop',
                'phone' => null,
                'address' => null,
                'join_date' => now()->toDateString(),
                'employee_type' => 'daily',
                'daily_rate' => 0,
                'piece_rate' => 0,
                'status' => 'active',
            ],
        );

        $workOrder = ProductionWorkOrder::query()->updateOrCreate(
            ['work_order_number' => 'WO-INIT'],
            [
                'product_id' => $finishedProduct->id,
                'sales_order_id' => $salesOrder?->id,
                'project_id' => $project?->id,
                'source_label' => 'SO-INIT',
                'stage' => 'draft',
                'target_qty' => 1,
                'completed_qty' => 0,
                'progress' => 0,
                'due_date' => now()->addDays(7)->toDateString(),
            ],
        );

        $workOrder->items()->updateOrCreate(
            ['product_id' => $materialProduct->id],
            [
                'quantity' => 1,
                'notes' => 'Initial material requirement baseline.',
            ],
        );

        $workOrder->logs()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'work_date' => now()->toDateString(),
                'stage' => 'cetak',
            ],
            [
                'made_qty' => 1,
                'reject_qty' => 0,
                'ok_qty' => 1,
                'piece_rate' => 0,
                'notes' => 'Initial production log baseline.',
                'verified_by' => $admin?->id,
                'verified_at' => now(),
            ],
        );

        $bom = Bom::query()->updateOrCreate(
            [
                'product_id' => $finishedProduct->id,
                'version' => 'v1',
            ],
            [
                'effective_from' => now()->toDateString(),
                'status' => 'active',
                'total_cost' => 500000,
            ],
        );

        $bom->items()->updateOrCreate(
            ['component_product_id' => $materialProduct->id],
            [
                'component_name' => $materialProduct->name,
                'quantity' => 1,
                'unit_id' => $kgUnit?->id,
                'unit_cost' => 500000,
                'subtotal' => 500000,
            ],
        );
    }
}
