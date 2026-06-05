<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyPaymentRequest extends FormRequest
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
            'verified_by' => ['sometimes', 'nullable', 'uuid', Rule::exists('users', 'id')],
            'verified_at' => ['required', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
