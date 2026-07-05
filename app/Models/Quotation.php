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
    'quotation_number',
    'customer_id',
    'created_by',
    'quotation_date',
    'valid_until',
    'subtotal',
    'tax_amount',
    'total',
    'status',
    'global_discount_type',
    'global_discount_value',
    'global_discount_amount',
    'notes',
    'cancelled_by',
    'cancelled_at',
    'cancel_reason',
])]
class Quotation extends Model
{
    use Cancellable, GeneratesDocumentNumber, HasFactory, HasUuids;

    public function documentNumberPrefix(): string
    {
        return 'QUO';
    }

    public function documentNumberField(): string
    {
        return 'quotation_number';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }

    protected function casts(): array
    {
        return [
            'quotation_date' => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }
}
