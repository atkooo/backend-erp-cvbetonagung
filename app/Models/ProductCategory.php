<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'status'])]
class ProductCategory extends Model
{
    use HasUuids;

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
