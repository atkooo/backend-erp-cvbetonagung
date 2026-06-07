<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesDocumentNumber;
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
    use HasUuids, GeneratesDocumentNumber;

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
