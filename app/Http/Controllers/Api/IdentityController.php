<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IdentityRequest;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class IdentityController extends Controller
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

    public function index(Request $request, string $resource): JsonResponse
    {
        if ($resource === 'role-permissions') {
            return $this->indexRolePermissions($request);
        }

        $config = $this->resourceConfig($resource);
        $perPage = max(1, min((int) $request->integer('per_page', 15), 100));
        $sort = $this->sortColumn($request, $config);
        $direction = $request->string('direction')->lower()->value() === 'desc' ? 'desc' : 'asc';

        $query = $this->query($config);
        $this->applyFilters($query, $request, $config);

        $paginator = $query
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

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

    public function store(IdentityRequest $request, string $resource): JsonResponse
    {
        if ($resource === 'role-permissions') {
            return $this->storeRolePermission($request);
        }

        $config = $this->resourceConfig($resource);
        $modelClass = $config['model'];
        $model = $modelClass::query()->create($request->validated());

        return response()->json(['data' => $model->fresh($config['relations'] ?? [])], 201);
    }

    public function show(string $resource, string $id): JsonResponse
    {
        $config = $this->resourceConfig($resource);

        return response()->json(['data' => $this->findModel($config, $id)]);
    }

    public function update(IdentityRequest $request, string $resource, string $id): JsonResponse
    {
        $config = $this->resourceConfig($resource);
        $model = $this->findModel($config, $id);

        $model->fill($request->validated());
        $model->save();

        return response()->json(['data' => $model->fresh($config['relations'] ?? [])]);
    }

    public function destroy(string $resource, string $id): JsonResponse|Response
    {
        $config = $this->resourceConfig($resource);
        $model = $this->findModel($config, $id);

        try {
            $model->delete();
        } catch (QueryException) {
            return response()->json([
                'message' => 'Record cannot be deleted because it is referenced by other ERP data.',
            ], 409);
        }

        return response()->noContent();
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

    /**
     * @param array<string, mixed> $config
     */
    private function query(array $config): Builder
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];

        return $modelClass::query()->with($config['relations'] ?? []);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function applyFilters(Builder $query, Request $request, array $config): void
    {
        foreach (['role_id', 'user_id', 'module', 'action', 'department', 'status', 'employee_type'] as $column) {
            if ($request->filled($column)) {
                $query->where($column, $request->string($column)->value());
            }
        }

        if (! $request->filled('q') || $config['searchable'] === []) {
            return;
        }

        $search = $request->string('q')->value();
        $query->where(function (Builder $query) use ($config, $search): void {
            foreach ($config['searchable'] as $column) {
                $query->orWhere($column, 'like', '%'.$search.'%');
            }
        });
    }

    /**
     * @param array<string, mixed> $config
     */
    private function findModel(array $config, string $id): Model
    {
        return $this->query($config)->whereKey($id)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function resourceConfig(string $resource): array
    {
        abort_unless(array_key_exists($resource, self::RESOURCES), 404, 'Unknown identity resource.');

        return self::RESOURCES[$resource];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function sortColumn(Request $request, array $config): string
    {
        $sort = $request->string('sort', Arr::first($config['sortable']))->value();

        return in_array($sort, $config['sortable'], true)
            ? $sort
            : Arr::first($config['sortable']);
    }
}
