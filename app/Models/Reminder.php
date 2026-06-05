<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'type',
    'reference_type',
    'reference_id',
    'reference_number',
    'division',
    'schedule_at',
    'priority',
    'status',
    'assigned_to',
])]
class Reminder extends Model
{
    use HasUuids;

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    protected function casts(): array
    {
        return [
            'schedule_at' => 'datetime',
        ];
    }
}
