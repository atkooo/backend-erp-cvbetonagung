<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'session_id',
    'product_id',
    'location_id',
    'system_qty',
    'physical_qty',
    'difference_qty',
    'notes',
    'approval_request_id',
])]
class StockOpnameItem extends Model
{
    use HasUuids;



    protected $appends = ['is_adjusted'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(StockOpnameSession::class, 'session_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'location_id');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function getIsAdjustedAttribute(): bool
    {
        if ((float) $this->difference_qty === 0.0) {
            return true;
        }

        return StockMovement::query()
            ->where('reference_type', 'stock_opname_item')
            ->where('reference_id', $this->id)
            ->exists();
    }

    protected function casts(): array
    {
        return [
            'system_qty' => 'decimal:2',
            'physical_qty' => 'decimal:2',
            'difference_qty' => 'decimal:2',
        ];
    }
}
