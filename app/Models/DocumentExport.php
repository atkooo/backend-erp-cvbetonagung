<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'document_type',
    'reference_type',
    'reference_id',
    'document_number',
    'export_format',
    'division',
    'exported_by',
    'exported_at',
])]
class DocumentExport extends Model
{
    use HasUuids;

    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    public function exportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by');
    }

    protected function casts(): array
    {
        return [
            'exported_at' => 'datetime',
        ];
    }
}
