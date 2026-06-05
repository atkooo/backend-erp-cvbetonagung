<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductionRequest extends FormRequest
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
            'work-orders' => [
                'work_order_number' => [...$required, 'string', 'max:255', Rule::unique('production_work_orders', 'work_order_number')->ignore($id)],
                'product_id' => [...$required, 'uuid', Rule::exists('products', 'id')],
                'sales_order_id' => [...$nullable, 'uuid', Rule::exists('sales_orders', 'id')],
                'project_id' => [...$nullable, 'uuid', Rule::exists('projects', 'id')],
                'source_label' => [...$nullable, 'string', 'max:255'],
                'stage' => [...$required, 'string', 'max:255'],
                'target_qty' => [...$required, 'numeric', 'gt:0'],
                'completed_qty' => ['sometimes', 'numeric', 'min:0'],
                'progress' => ['sometimes', 'integer', 'min:0', 'max:100'],
                'due_date' => [...$nullable, 'date'],
            ],
            'work-order-items' => [
                'work_order_id' => [...$required, 'uuid', Rule::exists('production_work_orders', 'id')],
                'product_id' => [...$required, 'uuid', Rule::exists('products', 'id')],
                'quantity' => [...$required, 'numeric', 'gt:0'],
                'notes' => [...$nullable, 'string'],
            ],
            'work-logs' => [
                'work_order_id' => [...$required, 'uuid', Rule::exists('production_work_orders', 'id')],
                'employee_id' => [...$nullable, 'uuid', Rule::exists('employees', 'id')],
                'work_date' => [...$required, 'date'],
                'stage' => [...$required, 'string', 'max:255'],
                'made_qty' => ['sometimes', 'numeric', 'min:0'],
                'reject_qty' => ['sometimes', 'numeric', 'min:0'],
                'ok_qty' => ['sometimes', 'numeric', 'min:0'],
                'piece_rate' => ['sometimes', 'numeric', 'min:0'],
                'notes' => [...$nullable, 'string'],
                'verified_by' => [...$nullable, 'uuid', Rule::exists('users', 'id')],
                'verified_at' => [...$nullable, 'date'],
            ],
            'boms' => [
                'product_id' => [...$required, 'uuid', Rule::exists('products', 'id')],
                'version' => [
                    ...$required,
                    'string',
                    'max:255',
                    Rule::unique('boms', 'version')
                        ->where('product_id', $this->input('product_id'))
                        ->ignore($id),
                ],
                'effective_from' => [...$nullable, 'date'],
                'status' => [...$required, Rule::in(['active', 'inactive'])],
                'total_cost' => ['sometimes', 'numeric', 'min:0'],
            ],
            'bom-items' => [
                'bom_id' => [...$required, 'uuid', Rule::exists('boms', 'id')],
                'component_product_id' => [...$nullable, 'uuid', Rule::exists('products', 'id')],
                'component_name' => [...$nullable, 'string', 'max:255'],
                'quantity' => [...$required, 'numeric', 'gt:0'],
                'unit_id' => [...$nullable, 'uuid', Rule::exists('units', 'id')],
                'unit_cost' => [...$required, 'numeric', 'min:0'],
                'subtotal' => [...$required, 'numeric', 'min:0'],
            ],
            default => [],
        };
    }
}
