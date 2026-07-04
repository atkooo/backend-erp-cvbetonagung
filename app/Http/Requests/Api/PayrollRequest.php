<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PayrollRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Use global middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $resource = $this->route('resource');

        return match ($resource) {
            'salary-components' => [
                'code' => 'required|string|max:50|unique:salary_components,code,'.$this->route('id'),
                'name' => 'required|string|max:255',
                'type' => 'required|in:allowance,deduction',
                'is_taxable' => 'boolean',
                'is_fixed' => 'boolean',
                'default_amount' => 'numeric|min:0',
            ],
            'employee-salaries' => [
                'employee_id' => 'required|exists:employees,id',
                'salary_component_id' => 'required|exists:salary_components,id',
                'amount' => 'required|numeric|min:0',
            ],
            'payrolls' => [
                'employee_id' => 'required|exists:employees,id',
                'period_month' => 'required|integer|min:1|max:12',
                'period_year' => 'required|integer|min:2000',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'status' => 'in:draft,approved,paid',
                'payment_date' => 'nullable|date',
                'notes' => 'nullable|string',
            ],
            'payroll-details' => [
                'payroll_id' => 'required|exists:payrolls,id',
                'salary_component_id' => 'required|exists:salary_components,id',
                'type' => 'required|in:allowance,deduction',
                'amount' => 'required|numeric|min:0',
                'notes' => 'nullable|string',
            ],
            'employee-loans' => [
                'employee_id' => 'required|exists:employees,id',
                'loan_number' => 'required|string|unique:employee_loans,loan_number,'.$this->route('id'),
                'amount' => 'required|numeric|min:0',
                'reason' => 'nullable|string',
                'date' => 'required|date',
                'status' => 'in:pending,approved,rejected,paid',
                'remaining_amount' => 'required|numeric|min:0',
                'installment_amount' => 'required|numeric|min:0',
                'notes' => 'nullable|string',
            ],
            'overtime-rules' => [
                'name' => 'required|string|max:255',
                'rate_per_hour' => 'required|numeric|min:0',
                'type' => 'in:weekday,weekend,holiday',
            ],
            'payroll-settings' => [
                'key' => 'required|string|unique:payroll_settings,key,'.$this->route('id'),
                'value' => 'required|array',
                'description' => 'nullable|string',
            ],
            default => []
        };
    }
}
