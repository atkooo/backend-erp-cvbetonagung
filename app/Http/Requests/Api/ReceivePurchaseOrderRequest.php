<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceivePurchaseOrderRequest extends FormRequest
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
            'to_location_id' => ['required', 'uuid', Rule::exists('storage_locations', 'id')],
            'handled_by' => ['sometimes', 'nullable', 'uuid', Rule::exists('users', 'id')],
            'movement_at' => ['required', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
