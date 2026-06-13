<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MasterDataRequest extends FormRequest
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
        $resource = (string) $this->route('resource');
        $id = $this->route('id');
        $required = $this->isMethod('post') ? ['required'] : ['sometimes', 'required'];
        $nullable = $this->isMethod('post') ? ['nullable'] : ['sometimes', 'nullable'];

        return match ($resource) {
            'customers' => [
                'code' => [...$nullable, 'string', 'max:255', Rule::unique('customers', 'code')->ignore($id)],
                'name' => [...$required, 'string', 'max:255'],
                'phone' => [...$nullable, 'string', 'max:255'],
                'email' => [...$nullable, 'email', 'max:255'],
                'city' => [...$nullable, 'string', 'max:255'],
                'address' => [...$nullable, 'string'],
                'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            ],
            'suppliers' => [
                'code' => [...$nullable, 'string', 'max:255', Rule::unique('suppliers', 'code')->ignore($id)],
                'name' => [...$required, 'string', 'max:255'],
                'contact_name' => [...$nullable, 'string', 'max:255'],
                'phone' => [...$nullable, 'string', 'max:255'],
                'city' => [...$nullable, 'string', 'max:255'],
                'address' => [...$nullable, 'string'],
                'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            ],
            'product-categories' => [
                'name' => [...$required, 'string', 'max:255', Rule::unique('product_categories', 'name')->ignore($id)],
                'description' => [...$nullable, 'string'],
                'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            ],
            'units' => [
                'code' => [...$required, 'string', 'max:255', Rule::unique('units', 'code')->ignore($id)],
                'name' => [...$required, 'string', 'max:255'],
                'type' => [...$nullable, 'string', Rule::in(['raw_material', 'finished_good', 'both'])],
            ],
            'warehouses' => [
                'code' => [...$nullable, 'string', 'max:255', Rule::unique('warehouses', 'code')->ignore($id)],
                'name' => [...$required, 'string', 'max:255'],
                'type' => [...$nullable, 'string', 'max:255'],
                'address' => [...$nullable, 'string'],
                'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            ],
            'storage-locations' => [
                'warehouse_id' => [...$required, 'uuid', Rule::exists('warehouses', 'id')],
                'code' => [
                    ...$nullable,
                    'string',
                    'max:255',
                    Rule::unique('storage_locations', 'code')
                        ->where('warehouse_id', $this->input('warehouse_id'))
                        ->ignore($id),
                ],
                'name' => [...$required, 'string', 'max:255'],
                'description' => [...$nullable, 'string'],
            ],
            'products' => [
                'category_id' => [...$nullable, 'uuid', Rule::exists('product_categories', 'id')],
                'unit_id' => [...$nullable, 'uuid', Rule::exists('units', 'id')],
                'sku' => [...$nullable, 'string', 'max:255', Rule::unique('products', 'sku')->ignore($id)],
                'type' => [...$nullable, 'string', Rule::in(['raw_material', 'finished_good', 'service'])],
                'name' => [...$required, 'string', 'max:255'],
                'is_customizable' => ['sometimes', 'boolean'],
                'pricing_method' => ['sometimes', Rule::in(['per_item', 'per_dimension'])],
                'cost_price' => ['sometimes', 'numeric', 'min:0'],
                'selling_price' => ['sometimes', 'numeric', 'min:0'],
                'min_stock' => ['sometimes', 'numeric', 'min:0'],
                'stock_status' => ['sometimes', Rule::in(['safe', 'low', 'empty'])],
                'qr_value' => [...$nullable, 'string', 'max:255', Rule::unique('products', 'qr_value')->ignore($id)],
                'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            ],
            'company-settings' => [
                'company_name' => [...$required, 'string', 'max:255'],
                'company_address' => [...$nullable, 'string'],
                'contact_phone' => [...$nullable, 'string', 'max:255'],
                'operational_email' => [...$nullable, 'email', 'max:255'],
                'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
                'backup_schedule' => [...$nullable, 'string', 'max:255'],
                'updated_by' => [...$nullable, 'uuid', Rule::exists('users', 'id')],
            ],
            default => [],
        };
    }
}
