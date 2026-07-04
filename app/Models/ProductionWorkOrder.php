<?php

namespace App\Models;

use App\Traits\GeneratesDocumentNumber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'work_order_number',
    'product_id',
    'sales_order_id',
    'project_id',
    'source_label',
    'stage',
    'target_qty',
    'completed_qty',
    'progress',
    'due_date',
])]
class ProductionWorkOrder extends Model
{
    use GeneratesDocumentNumber, HasUuids;

    protected static function booted(): void
    {
        static::created(function (ProductionWorkOrder $workOrder) {
            $tasks = [
                ['name' => 'Cetak & Curing', 'code_prefix' => 'TSK1'],
                ['name' => 'Finishing', 'code_prefix' => 'TSK2'],
                ['name' => 'QC', 'code_prefix' => 'TSK3'],
                ['name' => 'Siap Gudang', 'code_prefix' => 'TSK4'],
            ];

            $seq = 1;
            foreach ($tasks as $task) {
                // Generate a unique short code, e.g., TSK1-202606-0001
                $woNum = str_replace('WO-', '', $workOrder->work_order_number);
                $taskCode = $task['code_prefix'].'-'.$woNum;

                ProductionWorkOrderTask::create([
                    'work_order_id' => $workOrder->id,
                    'task_code' => $taskCode,
                    'task_name' => $task['name'],
                    'status' => 'Pending',
                    'sequence' => $seq++,
                    'target_qty' => $workOrder->target_qty,
                ]);
            }

            // Integrasi Cost ke RAB Proyek
            if ($workOrder->project_id) {
                $bom = Bom::where('product_id', $workOrder->product_id)
                    ->orderBy('effective_from', 'desc')
                    ->first();

                $unitCost = $bom ? $bom->total_cost : 0;
                $estimatedCost = $unitCost * $workOrder->target_qty;

                ProjectBudgetItem::create([
                    'project_id' => $workOrder->project_id,
                    'component' => 'Biaya Produksi '.$workOrder->work_order_number,
                    'budget_amount' => $estimatedCost,
                    'actual_amount' => 0,
                    'notes' => 'Otomatis di-generate dari Work Order '.$workOrder->work_order_number,
                ]);
            }
        });

        static::updated(function (ProductionWorkOrder $workOrder) {
            // Update actual_amount di RAB Proyek jika completed_qty atau target_qty berubah
            if ($workOrder->project_id && ($workOrder->isDirty('completed_qty') || $workOrder->isDirty('target_qty'))) {
                $budgetItem = ProjectBudgetItem::where('project_id', $workOrder->project_id)
                    ->where('component', 'Biaya Produksi '.$workOrder->work_order_number)
                    ->first();

                if ($budgetItem) {
                    $bom = Bom::where('product_id', $workOrder->product_id)
                        ->orderBy('effective_from', 'desc')
                        ->first();

                    $unitCost = $bom ? $bom->total_cost : 0;

                    $budgetItem->update([
                        'budget_amount' => $unitCost * $workOrder->target_qty, // menyesuaikan target jika berubah
                        'actual_amount' => $unitCost * $workOrder->completed_qty,
                    ]);
                }
            }
        });
    }

    public function documentNumberPrefix(): string
    {
        return 'WO';
    }

    public function documentNumberField(): string
    {
        return 'work_order_number';
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionWorkOrderItem::class, 'work_order_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProductionWorkOrderTask::class, 'work_order_id')->orderBy('sequence');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ProductionWorkLog::class, 'work_order_id');
    }

    protected function casts(): array
    {
        return [
            'target_qty' => 'decimal:2',
            'completed_qty' => 'decimal:2',
            'progress' => 'integer',
            'due_date' => 'date',
        ];
    }
}
