<?php

namespace App\Models;

use App\Traits\Cancellable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_order_id',
    'supplier_id',
    'payable_number',
    'due_date',
    'amount',
    'paid_amount',
    'status',
    'cancelled_by',
    'cancelled_at',
    'cancel_reason',
])]
class SupplierPayable extends Model
{
    use Cancellable, HasFactory, HasUuids;

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Hitung status payable berdasarkan jumlah yang sudah dibayar.
     */
    public static function resolveStatus(float $paidAmount, float $amount): string
    {
        if ($paidAmount <= 0) {
            return 'open';
        }

        return $paidAmount >= $amount ? 'paid' : 'partial';
    }

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }
}
