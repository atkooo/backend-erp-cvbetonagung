<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class HrdRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
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
            'employee-details' => [
                'employee_id' => ['required', 'uuid', 'exists:employees,id'],
                'type' => ['required', 'string', 'in:family,education,emergency_contact'],
                'name' => ['required', 'string', 'max:255'],
                'relation' => ['nullable', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:50'],
                'institution' => ['nullable', 'string', 'max:255'],
                'degree' => ['nullable', 'string', 'max:255'],
                'year_start' => ['nullable', 'string', 'max:4'],
                'year_end' => ['nullable', 'string', 'max:4'],
                'notes' => ['nullable', 'string'],
            ],
            'employee-documents' => [
                'employee_id' => ['required', 'uuid', 'exists:employees,id'],
                'document_type' => ['required', 'string', 'max:255'],
                'file_path' => ['required', 'string'],
                'file_name' => ['required', 'string', 'max:255'],
                'expiry_date' => ['nullable', 'date'],
            ],
            'leave-types' => [
                'code' => ['required', 'string', 'max:50'],
                'name' => ['required', 'string', 'max:255'],
                'is_paid' => ['boolean'],
                'max_days' => ['integer', 'min:0'],
                'description' => ['nullable', 'string'],
            ],
            'leaves' => [
                'employee_id' => ['required', 'uuid', 'exists:employees,id'],
                'leave_type_id' => ['required', 'uuid', 'exists:leave_types,id'],
                'start_date' => ['required', 'date'],
                'end_date' => ['required', 'date', 'after_or_equal:start_date'],
                'total_days' => ['required', 'integer', 'min:1'],
                'reason' => ['required', 'string'],
                'attachment_path' => ['nullable', 'string'],
                'status' => ['string', 'in:pending,approved,rejected'],
            ],
            'attendances' => [
                'employee_id' => ['required', 'uuid', 'exists:employees,id'],
                'date' => ['required', 'date'],
                'clock_in' => ['nullable', 'date_format:H:i:s'],
                'clock_out' => ['nullable', 'date_format:H:i:s'],
                'status' => ['string', 'in:present,late,absent,half_day,leave'],
                'late_minutes' => ['numeric', 'min:0'],
                'notes' => ['nullable', 'string'],
            ],
            default => [],
        };
    }
}
