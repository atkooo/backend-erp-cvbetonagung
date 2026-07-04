<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name', 'type'])]
class Unit extends Model
{
    use HasUuids, SoftDeletes;

    public const UPDATED_AT = null;

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function bomItems(): HasMany
    {
        return $this->hasMany(BomItem::class);
    }
}
