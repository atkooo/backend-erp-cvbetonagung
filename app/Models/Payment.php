<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesDocumentNumber;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'invoice_id',
    'account_id',
    'payment_number',
    'payment_date',
    'method',
    'amount',
    'status',
    'verified_by',
    'verified_at',
    'notes',
])]
class Payment extends Model
{
    use HasUuids, GeneratesDocumentNumber;

    public function documentNumberPrefix(): string
    {
        return 'PAY';
    }

    public function documentNumberField(): string
    {
        return 'payment_number';
    }

    public const UPDATED_AT = null;

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    protected function casts(): array
    {
        return [
            'payment_date' => 'datetime',
            'amount' => 'decimal:2',
            'verified_at' => 'datetime',
        ];
    }
}
