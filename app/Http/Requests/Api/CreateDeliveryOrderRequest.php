<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateDeliveryOrderRequest extends FormRequest
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
            'delivery_number' => ['nullable', 'string', 'max:255', Rule::unique('delivery_orders', 'delivery_number')],
            'delivery_date' => ['nullable', 'date'],
            'receiver_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['ready_to_load', 'shipped', 'received', 'cancelled'])],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
