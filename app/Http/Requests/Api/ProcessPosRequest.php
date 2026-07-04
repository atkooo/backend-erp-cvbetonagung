<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ProcessPosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'uuid', 'exists:customers,id'],
            'transaction_date' => ['nullable', 'date'],
            'fulfillment_type' => ['nullable', 'string', 'in:take_away,delivery'],
            'payment_account_id' => ['required', 'uuid', 'exists:accounts,id'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'items.*.location_id' => ['required', 'uuid', 'exists:storage_locations,id'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.specification' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.fulfillment_type' => ['nullable', 'string', 'in:take_away,delivery'],
            'handled_by' => ['nullable', 'string'],
            'global_discount_type' => ['nullable', 'string', 'in:percentage,nominal'],
            'global_discount_value' => ['nullable', 'numeric', 'min:0'],
            'global_discount_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
