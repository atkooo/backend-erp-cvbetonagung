<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_name',
    'company_address',
    'contact_phone',
    'operational_email',
    'tax_rate',
    'backup_schedule',
    'updated_by',
])]
class CompanySetting extends Model
{
    use HasUuids;

    public const CREATED_AT = null;

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected function casts(): array
    {
        return [
            'tax_rate' => 'decimal:2',
        ];
    }
}
