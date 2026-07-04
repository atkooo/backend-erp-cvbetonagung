<?php

namespace App\Models;

use App\Traits\GeneratesDocumentNumber;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoodsReceiptNote extends Model
{
    use GeneratesDocumentNumber, HasFactory, HasUuids;

    public function documentNumberPrefix(): string
    {
        return 'GRN';
    }

    public function documentNumberField(): string
    {
        return 'grn_number';
    }

    protected $fillable = [
        'grn_number',
        'purchase_order_id',
        'warehouse_id',
        'to_location_id',
        'received_by',
        'receipt_date',
        'delivery_order_number',
        'status',
        'notes',
    ];

    protected $casts = [
        'receipt_date' => 'date',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function toLocation()
    {
        return $this->belongsTo(StorageLocation::class, 'to_location_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items()
    {
        return $this->hasMany(GoodsReceiptNoteItem::class);
    }
}
