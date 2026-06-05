<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SupportRequest;
use App\Models\AuditLog;
use App\Models\DocumentExport;
use App\Models\Reminder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class SupportController extends Controller
{
    /**
     * @var array<string, array{model: class-string<Model>, searchable: array<int, string>, sortable: array<int, string>, relations?: array<int, string>}>
     */
    private const RESOURCES = [
        'audit-logs' => [
            'model' => AuditLog::class,
            'searchable' => ['action', 'object_type', 'object_number', 'summary', 'ip_address'],
            'sortable' => ['created_at', 'action', 'object_type', 'object_number'],
            'relations' => ['user', 'role'],
        ],
        'reminders' => [
            'model' => Reminder::class,
            'searchable' => ['type', 'reference_type', 'reference_number', 'division'],
            'sortable' => ['schedule_at', 'priority', 'status', 'type', 'created_at'],
            'relations' => ['assignedTo'],
        ],
        'document-exports' => [
            'model' => DocumentExport::class,
            'searchable' => ['document_type', 'reference_type', 'document_number', 'division', 'export_format'],
            'sortable' => ['exported_at', 'document_type', 'document_number', 'export_format'],
            'relations' => ['exportedBy'],
        ],
    ];

    public function index(Request $request, string $resource): JsonResponse
    {
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

    public function store(SupportRequest $request, string $resource): JsonResponse
    {
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

    public function update(SupportRequest $request, string $resource, string $id): JsonResponse
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
        foreach ([
            'user_id',
            'role_id',
            'object_type',
            'reference_type',
            'reference_id',
            'division',
            'priority',
            'status',
            'assigned_to',
            'exported_by',
            'export_format',
        ] as $column) {
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
        abort_unless(array_key_exists($resource, self::RESOURCES), 404, 'Unknown support resource.');

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
