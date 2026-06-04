<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'employee_number',
    'user_id',
    'name',
    'role_name',
    'department',
    'phone',
    'address',
    'join_date',
    'employee_type',
    'daily_rate',
    'piece_rate',
    'status',
])]
class Employee extends Model
{
    use HasUuids;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function productionWorkLogs(): HasMany
    {
        return $this->hasMany(ProductionWorkLog::class);
    }

    protected function casts(): array
    {
        return [
            'join_date' => 'date',
            'daily_rate' => 'decimal:2',
            'piece_rate' => 'decimal:2',
        ];
    }
}
