<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'target_location_id' => ['required', 'uuid'],
            'source_location_id' => ['nullable', 'uuid'],
            'handled_by' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'movement_at' => ['nullable', 'date'],
        ];
    }
}
