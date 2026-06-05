<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupportRequest extends FormRequest
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
        $required = $this->isMethod('post') ? ['required'] : ['sometimes', 'required'];
        $nullable = $this->isMethod('post') ? ['nullable'] : ['sometimes', 'nullable'];

        return match ($resource) {
            'audit-logs' => [
                'user_id' => [...$nullable, 'uuid', Rule::exists('users', 'id')],
                'role_id' => [...$nullable, 'uuid', Rule::exists('roles', 'id')],
                'action' => [...$required, 'string', 'max:255'],
                'object_type' => [...$required, 'string', 'max:255'],
                'object_id' => [...$nullable, 'uuid'],
                'object_number' => [...$nullable, 'string', 'max:255'],
                'summary' => [...$nullable, 'string'],
                'ip_address' => [...$nullable, 'ip'],
                'created_at' => [...$required, 'date'],
            ],
            'reminders' => [
                'type' => [...$required, 'string', 'max:255'],
                'reference_type' => [...$nullable, 'string', 'max:255'],
                'reference_id' => [...$nullable, 'uuid'],
                'reference_number' => [...$nullable, 'string', 'max:255'],
                'division' => [...$nullable, 'string', 'max:255'],
                'schedule_at' => [...$nullable, 'date'],
                'priority' => ['sometimes', Rule::in(['low', 'medium', 'high'])],
                'status' => ['sometimes', Rule::in(['open', 'done', 'cancelled'])],
                'assigned_to' => [...$nullable, 'uuid', Rule::exists('users', 'id')],
            ],
            'document-exports' => [
                'document_type' => [...$required, 'string', 'max:255'],
                'reference_type' => [...$nullable, 'string', 'max:255'],
                'reference_id' => [...$nullable, 'uuid'],
                'document_number' => [...$nullable, 'string', 'max:255'],
                'export_format' => [...$required, Rule::in(['pdf', 'xlsx', 'csv'])],
                'division' => [...$nullable, 'string', 'max:255'],
                'exported_by' => [...$nullable, 'uuid', Rule::exists('users', 'id')],
                'exported_at' => [...$nullable, 'date'],
            ],
            default => [],
        };
    }
}
