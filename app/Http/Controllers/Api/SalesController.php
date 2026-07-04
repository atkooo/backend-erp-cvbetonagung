<?php

namespace App\Http\Controllers\Api;

use App\Enums\DeliveryOrderStatus;
use App\Enums\SalesOrderStatus;
use App\Http\Requests\Api\ApproveQuotationRequest;
use App\Http\Requests\Api\ApproveSalesOrderRequest;
use App\Http\Requests\Api\CreateDeliveryOrderRequest;
use App\Http\Requests\Api\ProcessPosRequest;
use App\Http\Requests\Api\SalesRequest;
use App\Http\Requests\Api\ShipDeliveryOrderRequest;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Services\SalesWorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class SalesController extends ApiResourceController
{
    private const RESOURCES = [
        'quotations' => [
            'model' => Quotation::class,
            'searchable' => ['quotation_number', 'notes'],
            'sortable' => ['quotation_number', 'quotation_date', 'valid_until', 'status', 'total'],
            'relations' => ['customer', 'items.product.unit'],
        ],
        'quotation-items' => [
            'model' => QuotationItem::class,
            'searchable' => ['description'],
            'sortable' => ['quantity', 'unit_price', 'subtotal'],
            'relations' => ['quotation', 'product.unit'],
        ],
        'sales-orders' => [
            'model' => SalesOrder::class,
            'searchable' => ['order_number', 'notes'],
            'sortable' => ['order_number', 'order_date', 'status', 'total'],
            'relations' => ['customer', 'quotation', 'items.product.unit', 'deliveryOrders.items', 'invoices'],
        ],
        'sales-order-items' => [
            'model' => SalesOrderItem::class,
            'searchable' => ['description'],
            'sortable' => ['quantity', 'unit_price', 'delivered_qty', 'subtotal'],
            'relations' => ['salesOrder', 'product.unit'],
        ],
        'delivery-orders' => [
            'model' => DeliveryOrder::class,
            'searchable' => ['delivery_number', 'notes', 'shipping_address'],
            'sortable' => ['delivery_number', 'delivery_date', 'status'],
            'relations' => ['customer', 'salesOrder', 'items.product.unit', 'items.salesOrderItem'],
        ],
        'delivery-order-items' => [
            'model' => DeliveryOrderItem::class,
            'searchable' => ['notes'],
            'sortable' => ['quantity'],
            'relations' => ['deliveryOrder', 'salesOrderItem', 'product.unit'],
        ],
    ];

    protected function resources(): array
    {
        return self::RESOURCES;
    }

    public function index(Request $request, string $resource): JsonResponse
    {
        return $this->indexResource($request, $resource);
    }

    /**
     * Store — semua business logic didelegasikan ke SalesWorkflowService.
     */
    public function store(SalesRequest $request, string $resource, SalesWorkflowService $service): JsonResponse
    {
        $data = $request->validated();

        if ($resource === 'quotations') {
            $model = $service->createQuotation($data);

            return (new JsonResource($model))->response()->setStatusCode(201);
        }

        if ($resource === 'sales-orders') {
            $model = $service->createSalesOrder($data);

            return (new JsonResource($model))->response()->setStatusCode(201);
        }

        return $this->storeResource($resource, $data);
    }

    public function show(string $resource, string $id): JsonResponse
    {
        return $this->showResource($resource, $id);
    }

    /**
     * Update — semua business logic didelegasikan ke SalesWorkflowService.
     */
    public function update(SalesRequest $request, string $resource, string $id, SalesWorkflowService $service): JsonResponse
    {
        $data = $request->validated();

        if ($resource === 'quotations') {
            $model = $service->updateQuotation($id, $data);

            return (new JsonResource($model))->response();
        }

        if ($resource === 'sales-orders') {
            $model = $service->updateSalesOrder($id, $data);

            return (new JsonResource($model))->response();
        }

        if ($resource === 'delivery-orders') {
            $config = $this->resourceConfig($resource);
            $model = $this->findResourceModel($config, $id);

            // Validation for ready_to_load
            if (($data['status'] ?? '') === DeliveryOrderStatus::ReadyToLoad->value && $model->status !== DeliveryOrderStatus::ReadyToLoad->value) {
                $model->load('items.product');
                foreach ($model->items as $item) {
                    $service->checkProductStock($item->product_id, $item->quantity);
                }
            }

            DB::transaction(function () use ($model, $data) {
                $model->update($data);

                if (($data['status'] ?? '') === DeliveryOrderStatus::Received->value && $model->sales_order_id) {
                    $salesOrder = SalesOrder::query()->find($model->sales_order_id);
                    if ($salesOrder) {
                        $salesOrder->forceFill(['status' => SalesOrderStatus::Completed->value])->save();
                    }
                }
            });

            return (new JsonResource($model->fresh($config['relations'] ?? [])))->response();
        }

        return $this->updateResource($resource, $id, $data);
    }

    public function destroy(string $resource, string $id): JsonResponse|Response
    {
        return $this->destroyResource($resource, $id);
    }

    // -----------------------------------------------------------
    // Workflow actions (semua via SalesWorkflowService)
    // -----------------------------------------------------------

    public function approveQuotation(ApproveQuotationRequest $request, string $id, SalesWorkflowService $service): JsonResponse
    {
        $salesOrder = $service->approveQuotation($id, $request->validated());
        $config = $this->resourceConfig('sales-orders');

        return (new JsonResource($salesOrder->fresh($config['relations'] ?? [])))->response()->setStatusCode(201);
    }

    public function approveSalesOrder(ApproveSalesOrderRequest $request, string $id, SalesWorkflowService $service): JsonResponse
    {
        $salesOrder = $service->approveSalesOrder($id, $request->validated());
        $config = $this->resourceConfig('sales-orders');

        return (new JsonResource($salesOrder->fresh($config['relations'] ?? [])))->response()->setStatusCode(200);
    }

    public function createDeliveryOrder(CreateDeliveryOrderRequest $request, string $id, SalesWorkflowService $service): JsonResponse
    {
        $deliveryOrder = $service->createDeliveryOrder($id, $request->validated());
        $config = $this->resourceConfig('delivery-orders');

        return (new JsonResource($deliveryOrder->fresh($config['relations'] ?? [])))->response()->setStatusCode(201);
    }

    public function shipDeliveryOrder(ShipDeliveryOrderRequest $request, string $id, SalesWorkflowService $service): JsonResponse
    {
        $deliveryOrder = $service->shipDeliveryOrder($id, $request->validated());
        $config = $this->resourceConfig('delivery-orders');

        return (new JsonResource($deliveryOrder->fresh($config['relations'] ?? [])))->response()->setStatusCode(200);
    }

    public function processPos(ProcessPosRequest $request, SalesWorkflowService $service): JsonResponse
    {
        $validated = $request->validated();

        $salesOrder = $service->processPOS($validated);
        $config = $this->resourceConfig('sales-orders');

        return (new JsonResource($salesOrder->fresh($config['relations'] ?? [])))->response()->setStatusCode(201);
    }

    protected function filterableColumns(): array
    {
        return [
            'customer_id',
            'quotation_id',
            'sales_order_id',
            'product_id',
            'status',
            'source',
        ];
    }

    protected function applyFilters(Builder $query, Request $request, array $config): void
    {
        parent::applyFilters($query, $request, $config);

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $dateColumn = match ($config['model']) {
                Quotation::class => 'quotation_date',
                SalesOrder::class => 'order_date',
                DeliveryOrder::class => 'delivery_date',
                default => 'created_at',
            };
            $query->whereBetween($dateColumn, [$request->start_date, $request->end_date]);
        }
    }
}
