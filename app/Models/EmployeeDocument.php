<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'document_type',
    'file_path',
    'file_name',
    'expiry_date',
])]
class EmployeeDocument extends Model
{
    use HasUuids;

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
        ];
    }
}
