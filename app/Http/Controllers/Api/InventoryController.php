<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\AdjustStockOpnameItemRequest;
use App\Http\Requests\Api\InventoryRequest;
use App\Models\ApprovalRequest;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\StockOpnameItem;
use App\Models\StockOpnameSession;
use App\Services\InventoryWorkflowService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class InventoryController extends ApiResourceController
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
            'sortable' => ['system_qty', 'physical_qty', 'difference_qty', 'created_at'],
            'relations' => ['session', 'product.category', 'location', 'approvalRequest'],
        ],
        'approval-requests' => [
            'model' => ApprovalRequest::class,
            'searchable' => ['approval_number', 'request_type', 'reference_number', 'change_summary'],
            'sortable' => ['approval_number', 'request_type', 'status', 'requested_at', 'decided_at'],
            'relations' => ['requester', 'approver'],
        ],
    ];

    public function __construct(private readonly InventoryWorkflowService $inventoryWorkflow)
    {
    }

    /**
     * @return array<string, array{model: class-string<Model>, searchable: array<int, string>, sortable: array<int, string>, relations?: array<int, string>}>
     */
    protected function resources(): array
    {
        return self::RESOURCES;
    }

    public function index(Request $request, string $resource): JsonResponse
    {
        return $this->indexResource($request, $resource);
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

        return $this->storeResource($resource, $request->validated());
    }

    public function show(string $resource, string $id): JsonResponse
    {
        $this->resourceConfig($resource);
        abort_if($resource === 'product-stocks', 404);

        return $this->showResource($resource, $id);
    }

    public function showProductStock(string $productId, string $locationId): JsonResponse
    {
        return response()->json([
            'data' => $this->findProductStock($productId, $locationId),
        ]);
    }

    public function update(InventoryRequest $request, string $resource, string $id): JsonResponse
    {
        $this->resourceConfig($resource);
        abort_if($resource === 'product-stocks', 404);

        return $this->updateResource($resource, $id, $request->validated());
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
        $this->resourceConfig($resource);
        abort_if($resource === 'product-stocks', 404);

        return $this->destroyResource($resource, $id);
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

    public function adjustStockOpnameItem(AdjustStockOpnameItemRequest $request, string $id): JsonResponse
    {
        $item = $this->inventoryWorkflow->adjustStockOpnameItem($id, $request->validated());

        return response()->json([
            'data' => $item->fresh(['session', 'product', 'location', 'approvalRequest']),
        ]);
    }

    protected function filterableColumns(): array
    {
        return ['product_id', 'location_id', 'warehouse_id', 'session_id', 'status', 'type'];
    }

    private function findProductStock(string $productId, string $locationId): ProductStock
    {
        return ProductStock::query()
            ->with(['product', 'location'])
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->firstOrFail();
    }
}
