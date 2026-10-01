<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'stock_production_request_id',
    'product_id',
    'target_qty',
    'completed_qty',
    'notes',
])]
class StockProductionRequestItem extends Model
{
    use HasUuids;

    public function stockProductionRequest(): BelongsTo
    {
        return $this->belongsTo(StockProductionRequest::class, 'stock_production_request_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function casts(): array
    {
        return [
            'target_qty' => 'decimal:2',
            'completed_qty' => 'decimal:2',
        ];
    }
}
