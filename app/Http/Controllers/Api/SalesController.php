<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ApproveQuotationRequest;
use App\Http\Requests\Api\ApproveSalesOrderRequest;
use App\Http\Requests\Api\CreateDeliveryOrderRequest;
use App\Http\Requests\Api\SalesRequest;
use App\Http\Requests\Api\ShipDeliveryOrderRequest;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Services\SalesWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'relations' => ['customer', 'quotation', 'items.product.unit', 'deliveryOrders', 'invoices'],
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
            return response()->json(['data' => $model], 201);
        }

        if ($resource === 'sales-orders') {
            $model = $service->createSalesOrder($data);
            return response()->json(['data' => $model], 201);
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
            return response()->json(['data' => $model]);
        }

        if ($resource === 'sales-orders') {
            $model = $service->updateSalesOrder($id, $data);
            return response()->json(['data' => $model]);
        }

        if ($resource === 'delivery-orders') {
            $config = $this->resourceConfig($resource);
            $model = $this->findResourceModel($config, $id);

            DB::transaction(function () use ($model, $data) {
                $model->update($data);

                if (($data['status'] ?? '') === 'received' && $model->sales_order_id) {
                    $salesOrder = SalesOrder::query()->find($model->sales_order_id);
                    if ($salesOrder) {
                        $salesOrder->forceFill(['status' => 'completed'])->save();
                    }
                }
            });

            return response()->json(['data' => $model->fresh($config['relations'] ?? [])]);
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

        return response()->json(['data' => $salesOrder->fresh($config['relations'] ?? [])], 201);
    }

    public function approveSalesOrder(ApproveSalesOrderRequest $request, string $id, SalesWorkflowService $service): JsonResponse
    {
        $salesOrder = $service->approveSalesOrder($id, $request->validated());
        $config = $this->resourceConfig('sales-orders');

        return response()->json(['data' => $salesOrder->fresh($config['relations'] ?? [])], 200);
    }

    public function createDeliveryOrder(CreateDeliveryOrderRequest $request, string $id, SalesWorkflowService $service): JsonResponse
    {
        $deliveryOrder = $service->createDeliveryOrder($id, $request->validated());
        $config = $this->resourceConfig('delivery-orders');

        return response()->json(['data' => $deliveryOrder->fresh($config['relations'] ?? [])], 201);
    }

    public function shipDeliveryOrder(ShipDeliveryOrderRequest $request, string $id, SalesWorkflowService $service): JsonResponse
    {
        $deliveryOrder = $service->shipDeliveryOrder($id, $request->validated());
        $config = $this->resourceConfig('delivery-orders');

        return response()->json(['data' => $deliveryOrder->fresh($config['relations'] ?? [])], 200);
    }

    public function processPos(Request $request, SalesWorkflowService $service): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'uuid', 'exists:customers,id'],
            'transaction_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'items.*.location_id' => ['required', 'uuid', 'exists:storage_locations,id'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.specification' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'handled_by' => ['nullable', 'string'],
        ]);

        $salesOrder = $service->processPOS($validated);
        $config = $this->resourceConfig('sales-orders');

        return response()->json(['data' => $salesOrder->fresh($config['relations'] ?? [])], 201);
    }

    protected function filterableColumns(): array
    {
        return [
            'customer_id',
            'quotation_id',
            'sales_order_id',
            'product_id',
            'status',
        ];
    }
}
