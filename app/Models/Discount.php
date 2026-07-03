<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Discount extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'type',
        'value',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'value' => 'decimal:2',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
