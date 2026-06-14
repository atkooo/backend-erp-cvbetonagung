<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id',
    'task_code',
    'task_name',
    'status',
    'sequence',
    'target_date',
    'completed_date',
    'notes',
])]
class ProjectTask extends Model
{
    use HasUuids;

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    protected function casts(): array
    {
        return [
            'target_date' => 'date',
            'completed_date' => 'date',
            'sequence' => 'integer',
        ];
    }
}
