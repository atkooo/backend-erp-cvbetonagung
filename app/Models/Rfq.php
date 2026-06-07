<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesDocumentNumber;

class Rfq extends Model
{
    use HasFactory, HasUuids, GeneratesDocumentNumber;

    public function documentNumberPrefix(): string
    {
        return 'RFQ';
    }

    public function documentNumberField(): string
    {
        return 'rfq_number';
    }

    protected $fillable = [
        'rfq_number',
        'purchase_request_id',
        'supplier_id',
        'rfq_date',
        'valid_until',
        'status',
        'notes',
    ];

    protected $casts = [
        'rfq_date' => 'date',
        'valid_until' => 'date',
    ];

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(RfqItem::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
