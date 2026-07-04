<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'quotation_id',
    'product_id',
    'description',
    'piece_count',
    'length',
    'specification',
    'quantity',
    'unit_price',
    'discount_amount',
    'subtotal',
])]
class QuotationItem extends Model
{
    use HasUuids;

    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'piece_count' => 'decimal:2',
            'length' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }
}
