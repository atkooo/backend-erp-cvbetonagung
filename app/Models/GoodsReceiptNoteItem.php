<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoodsReceiptNoteItem extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $appends = [
        'received_quantity',
        'rejected_quantity',
    ];

    protected $fillable = [
        'goods_receipt_note_id',
        'purchase_order_item_id',
        'product_id',
        'received_qty',
        'rejected_qty',
        'notes',
    ];

    public function goodsReceiptNote()
    {
        return $this->belongsTo(GoodsReceiptNote::class);
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getReceivedQuantityAttribute()
    {
        return $this->received_qty;
    }

    public function getRejectedQuantityAttribute()
    {
        return $this->rejected_qty;
    }

    protected function casts(): array
    {
        return [
            'received_qty' => 'decimal:2',
            'rejected_qty' => 'decimal:2',
        ];
    }
}
