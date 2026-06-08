<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesRequest extends FormRequest
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
            'quotations' => [
                'quotation_number' => [...$nullable, 'string', 'max:255', Rule::unique('quotations', 'quotation_number')->ignore($id)],
                'customer_id' => [...$required, 'uuid', Rule::exists('customers', 'id')],
                'created_by' => [...$nullable, 'uuid', Rule::exists('users', 'id')],
                'quotation_date' => [...$required, 'date'],
                'valid_until' => [...$nullable, 'date', 'after_or_equal:quotation_date'],
                'subtotal' => ['sometimes', 'numeric', 'min:0'],
                'tax_amount' => ['sometimes', 'numeric', 'min:0'],
                'total' => ['sometimes', 'numeric', 'min:0'],
                'status' => ['sometimes', Rule::in(['draft', 'sent', 'approved', 'rejected'])],
                'notes' => [...$nullable, 'string'],
                'items' => ['sometimes', 'array', 'min:1'],
                'items.*.product_id' => [...$required, 'uuid', Rule::exists('products', 'id')],
                'items.*.description' => [...$nullable, 'string', 'max:255'],
                'items.*.quantity' => [...$required, 'numeric', 'gt:0'],
                'items.*.unit_price' => [...$required, 'numeric', 'min:0'],
            ],
            'quotation-items' => [
                'quotation_id' => [...$required, 'uuid', Rule::exists('quotations', 'id')],
                'product_id' => [...$required, 'uuid', Rule::exists('products', 'id')],
                'description' => [...$nullable, 'string', 'max:255'],
                'quantity' => [...$required, 'numeric', 'gt:0'],
                'unit_price' => [...$required, 'numeric', 'min:0'],
                'subtotal' => [...$required, 'numeric', 'min:0'],
            ],
            'sales-orders' => [
                'quotation_id' => [...$nullable, 'uuid', Rule::exists('quotations', 'id')],
                'order_number' => [...$nullable, 'string', 'max:255', Rule::unique('sales_orders', 'order_number')->ignore($id)],
                'customer_id' => [...$required, 'uuid', Rule::exists('customers', 'id')],
                'order_date' => [...$required, 'date'],
                'total' => ['sometimes', 'numeric', 'min:0'],
                'status' => ['sometimes', Rule::in(['draft', 'processing', 'completed', 'cancelled'])],
                'notes' => [...$nullable, 'string'],
                'items' => ['sometimes', 'array', 'min:1'],
                'items.*.product_id' => [...$required, 'uuid', Rule::exists('products', 'id')],
                'items.*.description' => [...$nullable, 'string', 'max:255'],
                'items.*.quantity' => [...$required, 'numeric', 'gt:0'],
                'items.*.unit_price' => [...$required, 'numeric', 'min:0'],
            ],
            'sales-order-items' => [
                'sales_order_id' => [...$required, 'uuid', Rule::exists('sales_orders', 'id')],
                'product_id' => [...$required, 'uuid', Rule::exists('products', 'id')],
                'description' => [...$nullable, 'string', 'max:255'],
                'quantity' => [...$required, 'numeric', 'gt:0'],
                'unit_price' => [...$required, 'numeric', 'min:0'],
                'subtotal' => [...$required, 'numeric', 'min:0'],
            ],
            'delivery-orders' => [
                'delivery_number' => [...$nullable, 'string', 'max:255', Rule::unique('delivery_orders', 'delivery_number')->ignore($id)],
                'sales_order_id' => [...$required, 'uuid', Rule::exists('sales_orders', 'id')],
                'customer_id' => [...$required, 'uuid', Rule::exists('customers', 'id')],
                'delivery_date' => [...$nullable, 'date'],
                'received_at' => [...$nullable, 'date'],
                'receiver_name' => [...$nullable, 'string', 'max:255'],
                'status' => ['sometimes', Rule::in(['ready_to_load', 'shipped', 'received', 'cancelled'])],
                'notes' => [...$nullable, 'string'],
            ],
            'delivery-order-items' => [
                'delivery_order_id' => [...$required, 'uuid', Rule::exists('delivery_orders', 'id')],
                'sales_order_item_id' => [...$nullable, 'uuid', Rule::exists('sales_order_items', 'id')],
                'product_id' => [...$required, 'uuid', Rule::exists('products', 'id')],
                'quantity' => [...$required, 'numeric', 'gt:0'],
            ],
            default => [],
        };
    }
}
