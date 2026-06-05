<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustStockOpnameItemRequest extends FormRequest
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
            'handled_by' => ['sometimes', 'nullable', 'uuid', Rule::exists('users', 'id')],
            'movement_at' => ['required', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
