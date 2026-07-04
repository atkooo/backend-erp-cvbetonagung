<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'work_order_id',
    'employee_id',
    'work_date',
    'stage',
    'made_qty',
    'reject_qty',
    'ok_qty',
    'piece_rate',
    'notes',
    'verified_by',
    'verified_at',
])]
class ProductionWorkLog extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::created(function (ProductionWorkLog $log) {
            $task = ProductionWorkOrderTask::where('work_order_id', $log->work_order_id)
                ->where('task_name', $log->stage)
                ->first();

            if ($task) {
                $task->completed_qty += $log->ok_qty;
                $task->reject_qty += $log->reject_qty;

                if ($task->completed_qty > 0) {
                    $task->status = 'In Progress';
                }
                if ($task->completed_qty >= $task->target_qty) {
                    $task->status = 'Completed';
                }
                $task->save();
            }
        });
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionWorkOrder::class, 'work_order_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'made_qty' => 'decimal:2',
            'reject_qty' => 'decimal:2',
            'ok_qty' => 'decimal:2',
            'piece_rate' => 'decimal:2',
            'verified_at' => 'datetime',
        ];
    }
}
