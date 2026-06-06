<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'date',
    'clock_in',
    'clock_out',
    'status',
    'late_minutes',
    'notes',
])]
class Attendance extends Model
{
    use HasUuids;

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'late_minutes' => 'decimal:2',
        ];
    }
}
