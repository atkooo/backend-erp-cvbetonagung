<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaySupplierPayableRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'gt:0'],
            'account_id' => ['required', 'uuid', Rule::exists('accounts', 'id')],
            'method' => ['sometimes', Rule::in(['cash', 'transfer', 'qris'])],
            'paid_by' => ['sometimes', 'nullable', 'uuid', Rule::exists('users', 'id')],
            'paid_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
