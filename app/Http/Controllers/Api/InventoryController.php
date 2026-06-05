<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\InventoryRequest;
use App\Models\ApprovalRequest;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\StockOpnameItem;
use App\Models\StockOpnameSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class InventoryController extends Controller
{
    /**
     * @var array<string, array{model: class-string<Model>, searchable: array<int, string>, sortable: array<int, string>, relations?: array<int, string>}>
     */
    private const RESOURCES = [
        'product-stocks' => [
            'model' => ProductStock::class,
            'searchable' => [],
            'sortable' => ['product_id', 'location_id', 'quantity', 'updated_at'],
            'relations' => ['product', 'location'],
        ],
        'stock-movements' => [
            'model' => StockMovement::class,
            'searchable' => ['reference_type', 'reference_number', 'notes'],
            'sortable' => ['movement_at', 'created_at', 'type', 'quantity'],
            'relations' => ['product', 'fromLocation', 'toLocation', 'handledBy'],
        ],
        'stock-opname-sessions' => [
            'model' => StockOpnameSession::class,
            'searchable' => ['opname_number', 'notes'],
            'sortable' => ['opname_number', 'status', 'started_at', 'closed_at'],
            'relations' => ['warehouse', 'startedBy'],
        ],
        'stock-opname-items' => [
            'model' => StockOpnameItem::class,
            'searchable' => ['notes'],
            'sortable' => ['system_qty', 'physical_qty', 'difference_qty'],
            'relations' => ['session', 'product', 'location', 'approvalRequest'],
        ],
        'approval-requests' => [
            'model' => ApprovalRequest::class,
            'searchable' => ['approval_number', 'request_type', 'reference_number', 'change_summary'],
            'sortable' => ['approval_number', 'request_type', 'status', 'requested_at', 'decided_at'],
            'relations' => ['requester', 'approver'],
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

    public function store(InventoryRequest $request, string $resource): JsonResponse
    {
        $config = $this->resourceConfig($resource);

        if ($resource === 'product-stocks') {
            $model = ProductStock::query()->updateOrCreate(
                Arr::only($request->validated(), ['product_id', 'location_id']),
                Arr::only($request->validated(), ['quantity']),
            );
            $model->load($config['relations'] ?? []);

            return response()->json(['data' => $model], 201);
        }

        $modelClass = $config['model'];
        $model = $modelClass::query()->create($request->validated());

        return response()->json(['data' => $model->fresh($config['relations'] ?? [])], 201);
    }

    public function show(string $resource, string $id): JsonResponse
    {
        $config = $this->resourceConfig($resource);
        abort_if($resource === 'product-stocks', 404);

        return response()->json(['data' => $this->findModel($config, $id)]);
    }

    public function showProductStock(string $productId, string $locationId): JsonResponse
    {
        return response()->json([
            'data' => $this->findProductStock($productId, $locationId),
        ]);
    }

    public function update(InventoryRequest $request, string $resource, string $id): JsonResponse
    {
        $config = $this->resourceConfig($resource);
        abort_if($resource === 'product-stocks', 404);

        $model = $this->findModel($config, $id);
        $model->fill($request->validated());
        $model->save();

        return response()->json(['data' => $model->fresh($config['relations'] ?? [])]);
    }

    public function updateProductStock(InventoryRequest $request, string $productId, string $locationId): JsonResponse
    {
        $stock = $this->findProductStock($productId, $locationId);
        $stock->fill(Arr::only($request->validated(), ['quantity']));
        $stock->save();
        $stock->load(['product', 'location']);

        return response()->json(['data' => $stock]);
    }

    public function destroy(string $resource, string $id): JsonResponse|Response
    {
        $config = $this->resourceConfig($resource);
        abort_if($resource === 'product-stocks', 404);

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

    public function destroyProductStock(string $productId, string $locationId): Response
    {
        $this->findProductStock($productId, $locationId);

        ProductStock::query()
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->delete();

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
        foreach (['product_id', 'location_id', 'warehouse_id', 'session_id', 'status', 'type'] as $column) {
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

    private function findProductStock(string $productId, string $locationId): ProductStock
    {
        return ProductStock::query()
            ->with(['product', 'location'])
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function resourceConfig(string $resource): array
    {
        abort_unless(array_key_exists($resource, self::RESOURCES), 404, 'Unknown inventory resource.');

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
