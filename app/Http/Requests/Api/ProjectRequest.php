<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
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
            'projects' => [
                'code' => [$required, 'string', 'max:255', Rule::unique('projects', 'code')->ignore($id)],
                'customer_id' => [$required, 'uuid', Rule::exists('customers', 'id')],
                'quotation_id' => [$nullable, 'uuid', Rule::exists('quotations', 'id')],
                'sales_order_id' => [$nullable, 'uuid', Rule::exists('sales_orders', 'id')],
                'project_name' => [$required, 'string', 'max:255'],
                'location' => [$nullable, 'string', 'max:255'],
                'project_type' => [$nullable, 'string', 'max:255'],
                'project_spec' => [$nullable, 'string', 'max:255'],
                'contract_value' => ['sometimes', 'numeric', 'min:0'],
                'deadline' => [$nullable, 'date'],
                'progress' => ['sometimes', 'integer', 'min:0', 'max:100'],
                'status' => ['sometimes', Rule::in([
                    'survey',
                    'quotation',
                    'deal',
                    'production',
                    'shipping',
                    'installation',
                    'completed',
                    'cancelled',
                ])],
            ],
            'project-timelines' => [
                'project_id' => [$required, 'uuid', Rule::exists('projects', 'id')],
                'event_date' => [$required, 'date'],
                'stage' => [$required, 'string', 'max:255'],
                'description' => [$nullable, 'string'],
                'icon' => [$nullable, 'string', 'max:255'],
                'created_by' => [$nullable, 'uuid', Rule::exists('users', 'id')],
            ],
            'project-documents' => [
                'project_id' => [$required, 'uuid', Rule::exists('projects', 'id')],
                'title' => [$required, 'string', 'max:255'],
                'file_url' => [$nullable, 'string'],
                'document_date' => [$nullable, 'date'],
                'uploaded_by' => [$nullable, 'uuid', Rule::exists('users', 'id')],
            ],
            'project-budget-items' => [
                'project_id' => [$required, 'uuid', Rule::exists('projects', 'id')],
                'component' => [$required, 'string', 'max:255'],
                'budget_amount' => ['sometimes', 'numeric', 'min:0'],
                'actual_amount' => ['sometimes', 'numeric', 'min:0'],
                'notes' => [$nullable, 'string'],
            ],
            default => [],
        };
    }
}
