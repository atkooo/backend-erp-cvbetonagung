<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_order_id',
    'product_id',
    'description',
    'quantity',
    'unit_price',
    'received_qty',
    'subtotal',
])]
class PurchaseOrderItem extends Model
{
    use HasUuids;

    public const CREATED_AT = null;
    public const UPDATED_AT = null;

    protected $appends = [
        'received_quantity',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'received_qty' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function getReceivedQuantityAttribute()
    {
        return $this->received_qty;
    }
}
