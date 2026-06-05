<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveQuotationRequest extends FormRequest
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
            'order_number' => ['required', 'string', 'max:255', Rule::unique('sales_orders', 'order_number')],
            'order_date' => ['required', 'date'],
            'status' => ['sometimes', Rule::in(['draft', 'processing', 'completed', 'cancelled'])],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
