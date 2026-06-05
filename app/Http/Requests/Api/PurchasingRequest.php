<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchasingRequest extends FormRequest
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
        $required = $this->isMethod('post') ? 'required' : 'sometimes|required';
        $nullable = $this->isMethod('post') ? 'nullable' : 'sometimes|nullable';

        return match ($resource) {
            'purchase-orders' => [
                'po_number' => [$required, 'string', 'max:255', Rule::unique('purchase_orders', 'po_number')->ignore($id)],
                'supplier_id' => [$required, 'uuid', Rule::exists('suppliers', 'id')],
                'po_date' => [$required, 'date'],
                'total' => ['sometimes', 'numeric', 'min:0'],
                'status' => ['sometimes', Rule::in(['draft', 'ordered', 'partially_received', 'fully_received', 'cancelled'])],
                'notes' => [$nullable, 'string'],
            ],
            'purchase-order-items' => [
                'purchase_order_id' => [$required, 'uuid', Rule::exists('purchase_orders', 'id')],
                'product_id' => [$required, 'uuid', Rule::exists('products', 'id')],
                'description' => [$nullable, 'string', 'max:255'],
                'quantity' => [$required, 'numeric', 'gt:0'],
                'unit_price' => [$required, 'numeric', 'min:0'],
                'received_qty' => ['sometimes', 'numeric', 'min:0'],
                'subtotal' => [$required, 'numeric', 'min:0'],
            ],
            'supplier-payables' => [
                'purchase_order_id' => [$nullable, 'uuid', Rule::exists('purchase_orders', 'id')],
                'supplier_id' => [$required, 'uuid', Rule::exists('suppliers', 'id')],
                'payable_number' => [$required, 'string', 'max:255', Rule::unique('supplier_payables', 'payable_number')->ignore($id)],
                'due_date' => [$nullable, 'date'],
                'amount' => [$required, 'numeric', 'min:0'],
                'paid_amount' => ['sometimes', 'numeric', 'min:0'],
                'status' => ['sometimes', Rule::in(['open', 'partial', 'paid', 'overdue', 'cancelled'])],
            ],
            'returns' => [
                'return_number' => [$required, 'string', 'max:255', Rule::unique('returns', 'return_number')->ignore($id)],
                'type' => [$required, Rule::in(['customer', 'supplier'])],
                'customer_id' => [$nullable, 'uuid', Rule::exists('customers', 'id')],
                'supplier_id' => [$nullable, 'uuid', Rule::exists('suppliers', 'id')],
                'sales_order_id' => [$nullable, 'uuid', Rule::exists('sales_orders', 'id')],
                'purchase_order_id' => [$nullable, 'uuid', Rule::exists('purchase_orders', 'id')],
                'reason' => [$required, 'string'],
                'qc_status' => [$required, 'string', 'max:255'],
                'created_by' => [$nullable, 'uuid', Rule::exists('users', 'id')],
            ],
            'return-items' => [
                'return_id' => [$required, 'uuid', Rule::exists('returns', 'id')],
                'product_id' => [$required, 'uuid', Rule::exists('products', 'id')],
                'quantity' => [$required, 'numeric', 'gt:0'],
                'notes' => [$nullable, 'string'],
            ],
            default => [],
        };
    }
}
