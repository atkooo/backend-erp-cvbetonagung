<?php

namespace App\Models;

use App\Traits\GeneratesDocumentNumber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'return_number',
    'type',
    'customer_id',
    'supplier_id',
    'sales_order_id',
    'purchase_order_id',
    'reason',
    'action',
    'qc_status',
    'created_by',
])]
class ProductReturn extends Model
{
    use GeneratesDocumentNumber, HasUuids;

    protected $table = 'returns';

    protected $attributes = [
        'qc_status' => 'pending_qc',
    ];

    public function documentNumberPrefix(): string
    {
        return 'RET';
    }

    public function documentNumberField(): string
    {
        return 'return_number';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }
}
