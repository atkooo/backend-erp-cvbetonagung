<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\IdentityRequest;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IdentityController extends ApiResourceController
{
    /**
     * @var array<string, array{model: class-string<Model>, searchable: array<int, string>, sortable: array<int, string>, relations?: array<int, string>}>
     */
    private const RESOURCES = [
        'roles' => [
            'model' => Role::class,
            'searchable' => ['code', 'name', 'description'],
            'sortable' => ['code', 'name', 'created_at'],
            'relations' => ['permissions'],
        ],
        'users' => [
            'model' => User::class,
            'searchable' => ['name', 'email'],
            'sortable' => ['name', 'email', 'status', 'last_login_at', 'created_at'],
            'relations' => ['role', 'employee'],
        ],
        'employees' => [
            'model' => Employee::class,
            'searchable' => ['employee_number', 'name', 'role_name', 'department', 'phone'],
            'sortable' => ['employee_number', 'name', 'department', 'employee_type', 'status', 'created_at'],
            'relations' => ['user'],
        ],
        'permissions' => [
            'model' => Permission::class,
            'searchable' => ['module', 'action', 'label'],
            'sortable' => ['module', 'action', 'created_at'],
            'relations' => ['roles'],
        ],
    ];

    /**
     * @return array<string, array{model: class-string<Model>, searchable: array<int, string>, sortable: array<int, string>, relations?: array<int, string>}>
     */
    protected function resources(): array
    {
        return self::RESOURCES;
    }

    public function index(Request $request, string $resource): JsonResponse
    {
        if ($resource === 'role-permissions') {
            return $this->indexRolePermissions($request);
        }

        return $this->indexResource($request, $resource);
    }

    public function store(IdentityRequest $request, string $resource): JsonResponse
    {
        if ($resource === 'role-permissions') {
            return $this->storeRolePermission($request);
        }

        return $this->storeResource($resource, $request->validated());
    }

    public function show(string $resource, string $id): JsonResponse
    {
        return $this->showResource($resource, $id);
    }

    public function update(IdentityRequest $request, string $resource, string $id): JsonResponse
    {
        return $this->updateResource($resource, $id, $request->validated());
    }

    public function destroy(string $resource, string $id): JsonResponse|Response
    {
        return $this->destroyResource($resource, $id);
    }

    public function showRolePermission(string $roleId, string $permissionId): JsonResponse
    {
        $role = Role::query()
            ->with(['permissions' => fn ($query) => $query->whereKey($permissionId)])
            ->whereKey($roleId)
            ->firstOrFail();

        $permission = $role->permissions->first();
        abort_if($permission === null, 404);

        return response()->json([
            'data' => [
                'role' => $role->only(['id', 'code', 'name']),
                'permission' => $permission->only(['id', 'module', 'action', 'label']),
                'access_level' => $permission->pivot->access_level,
            ],
        ]);
    }

    public function updateRolePermission(IdentityRequest $request, string $roleId, string $permissionId): JsonResponse
    {
        $validated = $request->validated();
        $role = Role::query()->findOrFail($roleId);
        Permission::query()->findOrFail($permissionId);

        $role->permissions()->syncWithoutDetaching([
            $permissionId => ['access_level' => $validated['access_level']],
        ]);

        return $this->showRolePermission($roleId, $permissionId);
    }

    public function destroyRolePermission(string $roleId, string $permissionId): Response
    {
        $role = Role::query()->findOrFail($roleId);
        $role->permissions()->detach($permissionId);

        return response()->noContent();
    }

    private function indexRolePermissions(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 15), 100));

        $query = Role::query()->with('permissions');

        if ($request->filled('role_id')) {
            $query->whereKey($request->string('role_id')->value());
        }

        $paginator = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    private function storeRolePermission(IdentityRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $role = Role::query()->findOrFail($validated['role_id']);
        Permission::query()->findOrFail($validated['permission_id']);

        $role->permissions()->syncWithoutDetaching([
            $validated['permission_id'] => ['access_level' => $validated['access_level']],
        ]);

        return $this->showRolePermission($validated['role_id'], $validated['permission_id'])
            ->setStatusCode(201);
    }

    protected function filterableColumns(): array
    {
        return ['role_id', 'user_id', 'module', 'action', 'department', 'status', 'employee_type'];
    }
}
