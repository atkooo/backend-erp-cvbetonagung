<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'location_id', 'quantity'])]
class ProductStock extends Model
{
    public $incrementing = false;

    public const CREATED_AT = null;

    protected $keyType = 'string';

    protected $primaryKey = 'product_id';

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'location_id');
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
        ];
    }

    protected function setKeysForSaveQuery($query)
    {
        return $query
            ->where('product_id', $this->getAttribute('product_id'))
            ->where('location_id', $this->getAttribute('location_id'));
    }
}
