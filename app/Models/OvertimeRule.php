<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OvertimeRule extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'rate_per_hour',
        'type',
    ];

    protected $casts = [
        'rate_per_hour' => 'decimal:2',
    ];
}
