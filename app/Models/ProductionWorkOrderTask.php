<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'work_order_id',
    'task_code',
    'task_name',
    'status',
    'assigned_to',
    'target_qty',
    'completed_qty',
    'reject_qty',
    'sequence',
])]
class ProductionWorkOrderTask extends Model
{
    use HasUuids;

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionWorkOrder::class, 'work_order_id');
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    protected function casts(): array
    {
        return [
            'target_qty' => 'decimal:2',
            'completed_qty' => 'decimal:2',
            'reject_qty' => 'decimal:2',
            'sequence' => 'integer',
        ];
    }
}
