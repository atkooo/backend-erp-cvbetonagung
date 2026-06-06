<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OvertimeRule extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'rate_per_hour',
        'type'
    ];

    protected $casts = [
        'rate_per_hour' => 'decimal:2',
    ];
}
