<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProductionRequest;
use App\Models\Bom;
use App\Models\BomItem;
use App\Models\ProductionWorkLog;
use App\Models\ProductionWorkOrder;
use App\Models\ProductionWorkOrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class ProductionController extends Controller
{
    /**
     * @var array<string, array{model: class-string<Model>, searchable: array<int, string>, sortable: array<int, string>, relations?: array<int, string>}>
     */
    private const RESOURCES = [
        'work-orders' => [
            'model' => ProductionWorkOrder::class,
            'searchable' => ['work_order_number', 'source_label', 'stage'],
            'sortable' => ['work_order_number', 'stage', 'target_qty', 'completed_qty', 'progress', 'due_date', 'created_at'],
            'relations' => ['product', 'salesOrder', 'project', 'items.product', 'logs.employee'],
        ],
        'work-order-items' => [
            'model' => ProductionWorkOrderItem::class,
            'searchable' => ['notes'],
            'sortable' => ['quantity'],
            'relations' => ['workOrder', 'product'],
        ],
        'work-logs' => [
            'model' => ProductionWorkLog::class,
            'searchable' => ['stage', 'notes'],
            'sortable' => ['work_date', 'stage', 'made_qty', 'reject_qty', 'ok_qty', 'created_at'],
            'relations' => ['workOrder', 'employee', 'verifiedBy'],
        ],
        'boms' => [
            'model' => Bom::class,
            'searchable' => ['version', 'status'],
            'sortable' => ['version', 'effective_from', 'status', 'total_cost', 'created_at'],
            'relations' => ['product', 'items.componentProduct', 'items.unit'],
        ],
        'bom-items' => [
            'model' => BomItem::class,
            'searchable' => ['component_name'],
            'sortable' => ['quantity', 'unit_cost', 'subtotal'],
            'relations' => ['bom', 'componentProduct', 'unit'],
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

    public function store(ProductionRequest $request, string $resource): JsonResponse
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

    public function update(ProductionRequest $request, string $resource, string $id): JsonResponse
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
            'product_id',
            'sales_order_id',
            'project_id',
            'work_order_id',
            'employee_id',
            'bom_id',
            'component_product_id',
            'unit_id',
            'stage',
            'status',
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
        abort_unless(array_key_exists($resource, self::RESOURCES), 404, 'Unknown production resource.');

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
