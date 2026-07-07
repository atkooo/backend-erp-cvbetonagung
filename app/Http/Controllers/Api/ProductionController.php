<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ProductionRequest;
use App\Models\Bom;
use App\Models\BomItem;
use App\Models\ProductionWorkLog;
use App\Models\ProductionWorkOrder;
use App\Models\ProductionWorkOrderItem;
use App\Models\ProductionWorkOrderTask;
use App\Services\CancellationService;
use App\Services\ProductionWorkflowService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;

class ProductionController extends ApiResourceController
{
    private ProductionWorkflowService $workflowService;

    private CancellationService $cancellationService;

    public function __construct(
        ProductionWorkflowService $workflowService,
        CancellationService $cancellationService
    ) {
        $this->workflowService = $workflowService;
        $this->cancellationService = $cancellationService;
    }

    /**
     * @var array<string, array{model: class-string<Model>, searchable: array<int, string>, sortable: array<int, string>, relations?: array<int, string>}>
     */
    private const RESOURCES = [
        'work-orders' => [
            'model' => ProductionWorkOrder::class,
            'searchable' => ['work_order_number', 'source_label', 'stage'],
            'sortable' => ['work_order_number', 'stage', 'target_qty', 'completed_qty', 'progress', 'due_date', 'created_at'],
            'relations' => ['product', 'salesOrder', 'salesOrder.customer', 'project', 'items.product', 'logs.employee', 'tasks.assignedEmployee'],
        ],
        'work-order-items' => [
            'model' => ProductionWorkOrderItem::class,
            'searchable' => ['notes'],
            'sortable' => ['quantity'],
            'relations' => ['workOrder', 'product'],
        ],
        'work-order-tasks' => [
            'model' => ProductionWorkOrderTask::class,
            'searchable' => ['task_code', 'task_name', 'status'],
            'sortable' => ['sequence', 'task_code', 'status', 'created_at'],
            'relations' => ['workOrder', 'assignedEmployee'],
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

    public function store(ProductionRequest $request, string $resource): JsonResponse
    {
        if ($resource === 'boms') {
            $model = $this->workflowService->createBom($request->validated());
            $config = $this->resourceConfig($resource);

            return (new JsonResource($model->fresh($config['relations'] ?? [])))->response()->setStatusCode(201);
        }

        return $this->storeResource($resource, $request->validated());
    }

    public function show(string $resource, string $id): JsonResponse
    {
        return $this->showResource($resource, $id);
    }

    public function update(ProductionRequest $request, string $resource, string $id): JsonResponse
    {
        if ($resource === 'boms') {
            $model = $this->workflowService->updateBom($id, $request->validated());
            $config = $this->resourceConfig($resource);

            return (new JsonResource($model->fresh($config['relations'] ?? [])))->response();
        }

        return $this->updateResource($resource, $id, $request->validated());
    }

    public function destroy(string $resource, string $id): JsonResponse|Response
    {
        return $this->destroyResource($resource, $id);
    }

    protected function filterableColumns(): array
    {
        return [
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
        ];
    }

    public function receive(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'target_location_id' => ['required', 'uuid'],
            'source_location_id' => ['nullable', 'uuid'],
            'handled_by' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'movement_at' => ['nullable', 'date'],
        ]);

        if (! isset($validated['movement_at'])) {
            $validated['movement_at'] = now()->toDateTimeString();
        }

        $workOrder = $this->workflowService->receiveWorkOrder($id, $validated);

        return response()->json([
            'message' => 'Work order received successfully',
            'data' => $workOrder,
        ]);
    }

    public function cancelWorkOrder(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string',
        ]);

        $userId = $request->user()?->id ?? 'system';
        $workOrder = $this->cancellationService->cancelWorkOrder($id, $userId, $validated['reason']);

        return response()->json([
            'message' => 'Work order cancelled successfully',
            'data' => $workOrder,
        ]);
    }
}
