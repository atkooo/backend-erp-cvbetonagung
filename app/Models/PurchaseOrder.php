<?php

namespace App\Models;

use App\Traits\Cancellable;
use App\Traits\GeneratesDocumentNumber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'po_number',
    'supplier_id',
    'sales_order_id',
    'purchase_request_id',
    'rfq_id',
    'po_date',
    'total',
    'status',
    'notes',
    'cancelled_by',
    'cancelled_at',
    'cancel_reason',
])]
class PurchaseOrder extends Model
{
    use Cancellable, GeneratesDocumentNumber, HasFactory, HasUuids;

    protected $appends = [
        'purchase_number',
        'order_date',
    ];

    public function documentNumberPrefix(): string
    {
        return 'PO';
    }

    public function documentNumberField(): string
    {
        return 'po_number';
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function supplierPayables(): HasMany
    {
        return $this->hasMany(SupplierPayable::class);
    }

    public function productReturns(): HasMany
    {
        return $this->hasMany(ProductReturn::class);
    }

    public function goodsReceiptNotes(): HasMany
    {
        return $this->hasMany(GoodsReceiptNote::class);
    }

    protected function casts(): array
    {
        return [
            'po_date' => 'date',
            'total' => 'decimal:2',
        ];
    }

    public function getPurchaseNumberAttribute()
    {
        return $this->po_number;
    }

    public function getOrderDateAttribute()
    {
        return $this->po_date;
    }
}
