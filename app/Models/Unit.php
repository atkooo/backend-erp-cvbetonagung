<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name'])]
class Unit extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
