<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

class FinanceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'account_id' => 'nullable|uuid',
            'type' => 'nullable|string|in:in,out',
            'category' => 'nullable|string|max:255',
            'search' => 'nullable|string|max:255',
        ];
    }
}
