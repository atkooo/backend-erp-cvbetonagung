<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\PayrollRequest;
use App\Models\EmployeeLoan;
use App\Models\EmployeeSalary;
use App\Models\OvertimeRule;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollSetting;
use App\Models\SalaryComponent;

class PayrollController extends ApiResourceController
{
    protected const RESOURCES = [
        'salary-components' => [
            'model' => SalaryComponent::class,
            'searchable' => ['code', 'name', 'type'],
        ],
        'employee-salaries' => [
            'model' => EmployeeSalary::class,
            'with' => ['employee', 'component'],
            'searchable' => [],
        ],
        'payrolls' => [
            'model' => Payroll::class,
            'with' => ['employee', 'details', 'details.component'],
            'searchable' => ['payroll_number', 'status'],
        ],
        'payroll-details' => [
            'model' => PayrollDetail::class,
            'with' => ['payroll', 'component'],
            'searchable' => ['type'],
        ],
        'employee-loans' => [
            'model' => EmployeeLoan::class,
            'with' => ['employee'],
            'searchable' => ['loan_number', 'status'],
        ],
        'overtime-rules' => [
            'model' => OvertimeRule::class,
            'searchable' => ['name', 'type'],
        ],
        'payroll-settings' => [
            'model' => PayrollSetting::class,
            'searchable' => ['key', 'description'],
        ],
    ];

    protected function getResourceConfig(string $resource): array
    {
        if (! isset(self::RESOURCES[$resource])) {
            abort(404, "Resource {$resource} not found");
        }

        return self::RESOURCES[$resource];
    }

    protected function getRequestClass(): string
    {
        return PayrollRequest::class;
    }
}
