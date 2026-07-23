<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

class InventoryReportRequest extends FormRequest
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
            'type' => 'nullable|string',
            'category_id' => 'nullable|uuid',
            'warehouse_id' => 'nullable|uuid',
            'product_id' => 'nullable|uuid',
            'stock_status' => 'nullable|string|in:aman,menipis,habis',
            'days' => 'nullable|integer|min:1',
            'search' => 'nullable|string|max:255',
        ];
    }
}
