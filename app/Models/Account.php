<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'code',
        'name',
        'type',
        'currency',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = ['balance'];

    public function getBalanceAttribute(): float
    {
        if (array_key_exists('balance', $this->attributes)) {
            return (float) $this->attributes['balance'];
        }

        return (float) $this->transactions()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE -amount END), 0) as total")
            ->value('total');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class, 'account_id');
    }
}
