<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryRequest extends FormRequest
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
        $resource = (string) ($this->route('resource') ?: 'product-stocks');
        $id = $this->route('id');
        $required = $this->isMethod('post') ? ['required'] : ['sometimes', 'required'];
        $nullable = $this->isMethod('post') ? ['nullable'] : ['sometimes', 'nullable'];

        return match ($resource) {
            'product-stocks' => [
                'product_id' => [...$required, 'uuid', Rule::exists('products', 'id')],
                'location_id' => [...$required, 'uuid', Rule::exists('storage_locations', 'id')],
                'quantity' => ['sometimes', 'numeric', 'min:0'],
            ],
            'stock-movements' => [
                'product_id' => [...$required, 'uuid', Rule::exists('products', 'id')],
                'from_location_id' => [...$nullable, 'uuid', Rule::exists('storage_locations', 'id')],
                'to_location_id' => [...$nullable, 'uuid', Rule::exists('storage_locations', 'id')],
                'type' => [...$required, Rule::in(['in', 'out', 'adjustment', 'transfer'])],
                'quantity' => [...$required, 'numeric', 'gt:0'],
                'reference_type' => [...$nullable, 'string', 'max:255'],
                'reference_id' => [...$nullable, 'uuid'],
                'reference_number' => [...$nullable, 'string', 'max:255'],
                'handled_by' => [...$nullable, 'uuid', Rule::exists('users', 'id')],
                'notes' => [...$nullable, 'string'],
                'movement_at' => [...$required, 'date'],
            ],
            'stock-opname-sessions' => [
                'opname_number' => [...$required, 'string', 'max:255', Rule::unique('stock_opname_sessions', 'opname_number')->ignore($id)],
                'warehouse_id' => [...$required, 'uuid', Rule::exists('warehouses', 'id')],
                'started_by' => [...$nullable, 'uuid', Rule::exists('users', 'id')],
                'status' => [...$required, Rule::in(['draft', 'in_progress', 'closed', 'cancelled'])],
                'started_at' => [...$nullable, 'date'],
                'closed_at' => [...$nullable, 'date', 'after_or_equal:started_at'],
                'notes' => [...$nullable, 'string'],
            ],
            'stock-opname-items' => [
                'session_id' => [...$required, 'uuid', Rule::exists('stock_opname_sessions', 'id')],
                'product_id' => [...$required, 'uuid', Rule::exists('products', 'id')],
                'location_id' => [...$required, 'uuid', Rule::exists('storage_locations', 'id')],
                'system_qty' => [...$required, 'numeric', 'min:0'],
                'physical_qty' => [...$required, 'numeric', 'min:0'],
                'difference_qty' => [...$required, 'numeric'],
                'notes' => [...$nullable, 'string'],
                'approval_request_id' => [...$nullable, 'uuid', Rule::exists('approval_requests', 'id')],
            ],
            'approval-requests' => [
                'approval_number' => [...$required, 'string', 'max:255', Rule::unique('approval_requests', 'approval_number')->ignore($id)],
                'request_type' => [...$required, 'string', 'max:255'],
                'requester_id' => [...$nullable, 'uuid', Rule::exists('users', 'id')],
                'approver_id' => [...$nullable, 'uuid', Rule::exists('users', 'id')],
                'reference_type' => [...$nullable, 'string', 'max:255'],
                'reference_id' => [...$nullable, 'uuid'],
                'reference_number' => [...$nullable, 'string', 'max:255'],
                'change_summary' => [...$nullable, 'string'],
                'amount' => [...$nullable, 'numeric', 'min:0'],
                'status' => ['sometimes', Rule::in(['pending', 'approved', 'rejected', 'cancelled'])],
                'requested_at' => [...$nullable, 'date'],
                'decided_at' => [...$nullable, 'date'],
                'decision_notes' => [...$nullable, 'string'],
            ],
            'bags' => [
                'date' => [...$required, 'date'],
                'warehouse_id' => [...$required, 'uuid', Rule::exists('warehouses', 'id')],
                'location_id' => [...$nullable, 'uuid', Rule::exists('storage_locations', 'id')],
                'type' => [...$required, Rule::in(['in', 'out', 'adjustment'])],
                'notes' => [...$nullable, 'string'],
                'items' => [...$required, 'array', 'min:1'],
                'items.*.product_id' => [...$required, 'uuid', Rule::exists('products', 'id')],
                'items.*.quantity' => [...$required, 'numeric', 'gt:0'],
                'items.*.notes' => [...$nullable, 'string'],
            ],
            default => [],
        };
    }
}
