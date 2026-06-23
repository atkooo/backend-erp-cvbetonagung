<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BagItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'bag_id',
        'product_id',
        'quantity',
        'notes',
    ];

    public function bag()
    {
        return $this->belongsTo(Bag::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
