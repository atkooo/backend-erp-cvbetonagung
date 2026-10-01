<?php

namespace App\Models;

use App\Traits\GeneratesDocumentNumber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'request_number',
    'storage_location_id',
    'requested_by',
    'approved_by',
    'request_date',
    'due_date',
    'status',
    'notes',
    'cancelled_by',
    'cancelled_at',
    'cancel_reason',
    'created_by',
])]
class StockProductionRequest extends Model
{
    use GeneratesDocumentNumber, HasUuids;

    public function documentNumberPrefix(): string
    {
        return 'SPR';
    }

    public function documentNumberField(): string
    {
        return 'request_number';
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockProductionRequestItem::class, 'stock_production_request_id');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(ProductionWorkOrder::class, 'stock_production_request_id');
    }

    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'storage_location_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'due_date' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }
}
