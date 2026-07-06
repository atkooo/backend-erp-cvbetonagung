<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReturnRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'sales_order_id' => 'nullable|uuid',
            'purchase_order_id' => 'nullable|uuid',
            'customer_id' => 'nullable|uuid',
            'supplier_id' => 'nullable|uuid',
            'type' => 'required|in:customer,supplier',
            'reason' => 'required|string',
            'action' => 'nullable|in:refund,replace',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|uuid',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.notes' => 'nullable|string',
        ];
    }
}
