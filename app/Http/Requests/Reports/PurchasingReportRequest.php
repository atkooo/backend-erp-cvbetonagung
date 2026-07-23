<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

class PurchasingReportRequest extends FormRequest
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
            'as_of_date' => 'nullable|date',
            'supplier_id' => 'nullable|uuid',
            'product_id' => 'nullable|uuid',
            'search' => 'nullable|string|max:255',
        ];
    }
}
