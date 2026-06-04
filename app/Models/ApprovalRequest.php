<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'approval_number',
    'request_type',
    'requester_id',
    'approver_id',
    'reference_type',
    'reference_id',
    'reference_number',
    'change_summary',
    'amount',
    'status',
    'requested_at',
    'decided_at',
    'decision_notes',
])]
class ApprovalRequest extends Model
{
    use HasUuids;

    public const CREATED_AT = null;
    public const UPDATED_AT = null;

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }
}
