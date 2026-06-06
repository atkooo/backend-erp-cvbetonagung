<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class EmployeeLoan extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'employee_id',
        'loan_number',
        'amount',
        'reason',
        'date',
        'status',
        'remaining_amount',
        'installment_amount',
        'notes'
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'installment_amount' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
