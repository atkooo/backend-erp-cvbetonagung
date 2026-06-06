<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'employee_number',
    'user_id',
    'name',
    'role_name',
    'department',
    'phone',
    'address',
    'join_date',
    'employee_type',
    'daily_rate',
    'piece_rate',
    'status',
    'gender',
    'place_of_birth',
    'date_of_birth',
    'marital_status',
    'religion',
    'blood_type',
    'id_card_number',
    'tax_id_number',
    'bank_name',
    'bank_account',
])]
class Employee extends Model
{
    use HasUuids;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function productionWorkLogs(): HasMany
    {
        return $this->hasMany(ProductionWorkLog::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(EmployeeDetail::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(EmployeeLoan::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    protected function casts(): array
    {
        return [
            'join_date' => 'date',
            'date_of_birth' => 'date',
            'daily_rate' => 'decimal:2',
            'piece_rate' => 'decimal:2',
        ];
    }
}
