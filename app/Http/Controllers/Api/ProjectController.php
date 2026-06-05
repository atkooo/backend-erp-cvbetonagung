<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProjectRequest;
use App\Models\Project;
use App\Models\ProjectBudgetItem;
use App\Models\ProjectDocument;
use App\Models\ProjectTimeline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class ProjectController extends Controller
{
    /**
     * @var array<string, array{model: class-string<Model>, searchable: array<int, string>, sortable: array<int, string>, relations?: array<int, string>}>
     */
    private const RESOURCES = [
        'projects' => [
            'model' => Project::class,
            'searchable' => ['code', 'project_name', 'location', 'project_type', 'project_spec'],
            'sortable' => ['code', 'project_name', 'deadline', 'progress', 'status', 'contract_value', 'created_at'],
            'relations' => ['customer', 'quotation', 'salesOrder', 'timelines', 'documents', 'budgetItems'],
        ],
        'project-timelines' => [
            'model' => ProjectTimeline::class,
            'searchable' => ['stage', 'description', 'icon'],
            'sortable' => ['event_date', 'stage', 'created_at'],
            'relations' => ['project', 'createdBy'],
        ],
        'project-documents' => [
            'model' => ProjectDocument::class,
            'searchable' => ['title', 'file_url'],
            'sortable' => ['title', 'document_date', 'created_at'],
            'relations' => ['project', 'uploadedBy'],
        ],
        'project-budget-items' => [
            'model' => ProjectBudgetItem::class,
            'searchable' => ['component', 'notes'],
            'sortable' => ['component', 'budget_amount', 'actual_amount', 'created_at'],
            'relations' => ['project'],
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

    public function store(ProjectRequest $request, string $resource): JsonResponse
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

    public function update(ProjectRequest $request, string $resource, string $id): JsonResponse
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
            'customer_id',
            'quotation_id',
            'sales_order_id',
            'project_id',
            'status',
            'project_type',
            'stage',
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
        abort_unless(array_key_exists($resource, self::RESOURCES), 404, 'Unknown project resource.');

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
