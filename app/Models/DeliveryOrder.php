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
    'delivery_number',
    'sales_order_id',
    'customer_id',
    'delivery_date',
    'received_at',
    'receiver_name',
    'status',
    'notes',
    'cancelled_by',
    'cancelled_at',
    'cancel_reason',
])]
class DeliveryOrder extends Model
{
    use Cancellable, GeneratesDocumentNumber, HasFactory, HasUuids;

    public function documentNumberPrefix(): string
    {
        return 'DO';
    }

    public function documentNumberField(): string
    {
        return 'delivery_number';
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryOrderItem::class);
    }

    protected function casts(): array
    {
        return [
            'delivery_date' => 'date',
            'received_at' => 'datetime',
        ];
    }
}
