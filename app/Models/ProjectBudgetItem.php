<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id',
    'component',
    'budget_amount',
    'actual_amount',
    'notes',
])]
class ProjectBudgetItem extends Model
{
    use HasUuids;

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    protected function casts(): array
    {
        return [
            'budget_amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
        ];
    }
}
