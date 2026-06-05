<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinanceRequest extends FormRequest
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
            'invoices' => [
                'sales_order_id' => [$nullable, 'uuid', Rule::exists('sales_orders', 'id')],
                'project_id' => [$nullable, 'uuid', Rule::exists('projects', 'id')],
                'invoice_number' => [$required, 'string', 'max:255', Rule::unique('invoices', 'invoice_number')->ignore($id)],
                'customer_id' => [$required, 'uuid', Rule::exists('customers', 'id')],
                'invoice_date' => [$required, 'date'],
                'due_date' => [$nullable, 'date', 'after_or_equal:invoice_date'],
                'subtotal' => ['sometimes', 'numeric', 'min:0'],
                'tax_amount' => ['sometimes', 'numeric', 'min:0'],
                'total' => ['sometimes', 'numeric', 'min:0'],
                'paid_amount' => ['sometimes', 'numeric', 'min:0'],
                'status' => ['sometimes', Rule::in(['unpaid', 'partial', 'paid', 'overdue', 'cancelled'])],
            ],
            'invoice-items' => [
                'invoice_id' => [$required, 'uuid', Rule::exists('invoices', 'id')],
                'product_id' => [$nullable, 'uuid', Rule::exists('products', 'id')],
                'description' => [$nullable, 'string', 'max:255'],
                'quantity' => [$required, 'numeric', 'gt:0'],
                'unit_price' => [$required, 'numeric', 'min:0'],
                'subtotal' => [$required, 'numeric', 'min:0'],
            ],
            'payments' => [
                'invoice_id' => [$required, 'uuid', Rule::exists('invoices', 'id')],
                'payment_number' => [$required, 'string', 'max:255', Rule::unique('payments', 'payment_number')->ignore($id)],
                'payment_date' => [$required, 'date'],
                'method' => [$required, Rule::in(['cash', 'transfer', 'qris'])],
                'amount' => [$required, 'numeric', 'gt:0'],
                'status' => ['sometimes', Rule::in(['pending', 'verified', 'failed'])],
                'verified_by' => [$nullable, 'uuid', Rule::exists('users', 'id')],
                'verified_at' => [$nullable, 'date'],
                'notes' => [$nullable, 'string'],
            ],
            'project-termins' => [
                'project_id' => [$required, 'uuid', Rule::exists('projects', 'id')],
                'phase' => [$required, 'string', 'max:255'],
                'amount' => [$required, 'numeric', 'min:0'],
                'due_date' => [$nullable, 'date'],
                'status' => ['sometimes', Rule::in(['unpaid', 'paid'])],
                'invoice_id' => [$nullable, 'uuid', Rule::exists('invoices', 'id')],
                'paid_at' => [$nullable, 'date'],
            ],
            default => [],
        };
    }
}
