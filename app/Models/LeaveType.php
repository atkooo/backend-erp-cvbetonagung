<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'is_paid',
    'max_days',
    'description',
])]
class LeaveType extends Model
{
    use HasUuids;

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
        ];
    }
}
