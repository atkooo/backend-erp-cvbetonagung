<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RfqItem extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'rfq_id',
        'product_id',
        'description',
        'quantity',
        'quoted_unit_price',
        'subtotal',
    ];

    public function rfq()
    {
        return $this->belongsTo(Rfq::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
