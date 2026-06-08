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
        $required = $this->isMethod('post') ? ['required'] : ['sometimes', 'required'];
        $nullable = $this->isMethod('post') ? ['nullable'] : ['sometimes', 'nullable'];

        return match ($resource) {
            'invoices' => [
                'invoice_number' => [...$nullable, 'string', 'max:255', Rule::unique('invoices', 'invoice_number')->ignore($id)],
                'sales_order_id' => [...$nullable, 'uuid', Rule::exists('sales_orders', 'id')],
                'project_id' => [...$nullable, 'uuid', Rule::exists('projects', 'id')],
                'customer_id' => [...$required, 'uuid', Rule::exists('customers', 'id')],
                'invoice_date' => [...$required, 'date'],
                'due_date' => [...$nullable, 'date', 'after_or_equal:invoice_date'],
                'subtotal' => ['sometimes', 'numeric', 'min:0'],
                'tax_amount' => ['sometimes', 'numeric', 'min:0'],
                'total' => ['sometimes', 'numeric', 'min:0'],
                'paid_amount' => ['sometimes', 'numeric', 'min:0'],
                'status' => ['sometimes', Rule::in(['unpaid', 'partial', 'paid', 'overdue', 'cancelled'])],
                'items' => ['sometimes', 'array', 'min:1'],
                'items.*.product_id' => [...$nullable, 'uuid', Rule::exists('products', 'id')],
                'items.*.description' => [...$nullable, 'string', 'max:255'],
                'items.*.quantity' => [...$required, 'numeric', 'gt:0'],
                'items.*.unit_price' => [...$required, 'numeric', 'min:0'],
            ],
            'invoice-items' => [
                'invoice_id' => [...$required, 'uuid', Rule::exists('invoices', 'id')],
                'product_id' => [...$nullable, 'uuid', Rule::exists('products', 'id')],
                'description' => [...$nullable, 'string', 'max:255'],
                'quantity' => [...$required, 'numeric', 'gt:0'],
                'unit_price' => [...$required, 'numeric', 'min:0'],
                'subtotal' => [...$required, 'numeric', 'min:0'],
            ],
            'payments' => [
                'invoice_id' => [...$required, 'uuid', Rule::exists('invoices', 'id')],
                'payment_number' => [...$nullable, 'string', 'max:255', Rule::unique('payments', 'payment_number')->ignore($id)],
                'payment_date' => [...$required, 'date'],
                'method' => [...$required, Rule::in(['cash', 'transfer', 'qris'])],
                'amount' => [...$required, 'numeric', 'gt:0'],
                'status' => ['sometimes', Rule::in(['pending', 'verified', 'failed'])],
                'verified_by' => [...$nullable, 'uuid', Rule::exists('users', 'id')],
                'verified_at' => [...$nullable, 'date'],
                'notes' => [...$nullable, 'string'],
            ],
            'project-termins' => [
                'project_id' => [...$required, 'uuid', Rule::exists('projects', 'id')],
                'phase' => [...$required, 'string', 'max:255'],
                'amount' => [...$required, 'numeric', 'min:0'],
                'due_date' => [...$nullable, 'date'],
                'status' => ['sometimes', Rule::in(['unpaid', 'paid'])],
                'invoice_id' => [...$nullable, 'uuid', Rule::exists('invoices', 'id')],
                'paid_at' => [...$nullable, 'date'],
            ],
            'accounts' => [
                'code' => [...$required, 'string', 'max:255', Rule::unique('accounts', 'code')->ignore($id)],
                'name' => [...$required, 'string', 'max:255'],
                'type' => [...$required, Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])],
                'balance' => ['sometimes', 'numeric'],
                'currency' => ['sometimes', 'string', 'size:3'],
                'description' => [...$nullable, 'string'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'cash-transactions' => [
                'transaction_number' => [...$required, 'string', 'max:255', Rule::unique('cash_transactions', 'transaction_number')->ignore($id)],
                'account_id' => [...$required, 'uuid', Rule::exists('accounts', 'id')],
                'transaction_date' => [...$required, 'date'],
                'type' => [...$required, Rule::in(['in', 'out'])],
                'amount' => [...$required, 'numeric', 'gt:0'],
                'category' => [...$required, 'string', 'max:255'],
                'description' => [...$nullable, 'string'],
                'reference_type' => [...$nullable, 'string', 'max:255'],
                'reference_id' => [...$nullable, 'uuid'],
                'recorded_by' => [...$nullable, 'uuid', Rule::exists('users', 'id')],
            ],
            default => [],
        };
    }
}
