<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Payroll extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'employee_id',
        'payroll_number',
        'period_month',
        'period_year',
        'start_date',
        'end_date',
        'total_attendance',
        'total_late_minutes',
        'basic_salary',
        'total_allowance',
        'total_deduction',
        'net_salary',
        'status',
        'payment_date',
        'notes'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'payment_date' => 'date',
        'basic_salary' => 'decimal:2',
        'total_allowance' => 'decimal:2',
        'total_deduction' => 'decimal:2',
        'net_salary' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function details()
    {
        return $this->hasMany(PayrollDetail::class);
    }
}
