<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IdentityRequest extends FormRequest
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
        $resource = (string) ($this->route('resource') ?: 'role-permissions');
        $id = $this->route('id');
        $usesPivotPath = $this->route('roleId') !== null && $this->route('permissionId') !== null;
        $required = $this->isMethod('post') ? ['required'] : ['sometimes', 'required'];
        $nullable = $this->isMethod('post') ? ['nullable'] : ['sometimes', 'nullable'];
        $pivotRequired = $usesPivotPath ? ['sometimes'] : $required;
        $pivotAccessLevel = $usesPivotPath ? ['required'] : $required;

        return match ($resource) {
            'roles' => [
                'code' => [...$required, 'string', 'max:255', Rule::unique('roles', 'code')->ignore($id)],
                'name' => [...$required, 'string', 'max:255', Rule::unique('roles', 'name')->ignore($id)],
                'description' => [...$nullable, 'string'],
            ],
            'users' => [
                'role_id' => [...$nullable, 'uuid', Rule::exists('roles', 'id')],
                'name' => [...$required, 'string', 'max:255'],
                'email' => [...$required, 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
                'password' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'min:8'],
                'status' => ['sometimes', Rule::in(['active', 'inactive'])],
                'last_login_at' => [...$nullable, 'date'],
            ],
            'employees' => [
                'business_unit' => [...$nullable, 'string', 'max:255'],
                'employee_number' => [...$nullable, 'string', 'max:255', Rule::unique('employees', 'employee_number')->ignore($id)],
                'user_id' => [...$nullable, 'uuid', Rule::exists('users', 'id')],
                'name' => [...$required, 'string', 'max:255'],
                'role_name' => [...$required, 'string', 'max:255'],
                'department' => [...$required, 'string', 'max:255'],
                'phone' => [...$nullable, 'string', 'max:255'],
                'address' => [...$nullable, 'string'],
                'join_date' => [...$nullable, 'date'],
                'employee_type' => ['sometimes', Rule::in(['permanent', 'contract', 'daily', 'borongan'])],
                'daily_rate' => ['sometimes', 'numeric', 'min:0'],
                'piece_rate' => ['sometimes', 'numeric', 'min:0'],
                'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            ],
            'permissions' => [
                'module' => [...$required, 'string', 'max:255'],
                'action' => [...$required, 'string', 'max:255'],
                'label' => [...$nullable, 'string', 'max:255'],
            ],
            'role-permissions' => [
                'role_id' => [...$pivotRequired, 'uuid', Rule::exists('roles', 'id')],
                'permission_id' => [...$pivotRequired, 'uuid', Rule::exists('permissions', 'id')],
                'access_level' => [...$pivotAccessLevel, Rule::in(['none', 'read', 'edit', 'full'])],
            ],
            default => [],
        };
    }
}
