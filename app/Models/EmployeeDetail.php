<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'type',
    'name',
    'relation',
    'phone',
    'institution',
    'degree',
    'year_start',
    'year_end',
    'notes',
])]
class EmployeeDetail extends Model
{
    use HasUuids;

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
