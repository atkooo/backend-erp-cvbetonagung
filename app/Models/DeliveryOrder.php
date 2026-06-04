<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
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
])]
class DeliveryOrder extends Model
{
    use HasUuids;

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
