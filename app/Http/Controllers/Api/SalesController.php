<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ApproveQuotationRequest;
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SalesController extends ApiResourceController
{
    /**
     * @var array<string, array{model: class-string<Model>, searchable: array<int, string>, sortable: array<int, string>, relations?: array<int, string>}>
     */
    private const RESOURCES = [
        'quotations' => [
            'model' => Quotation::class,
            'searchable' => ['quotation_number', 'notes'],
            'sortable' => ['quotation_number', 'quotation_date', 'valid_until', 'status', 'total', 'created_at'],
            'relations' => ['customer', 'createdBy', 'items.product'],
        ],
        'quotation-items' => [
            'model' => QuotationItem::class,
            'searchable' => ['description'],
            'sortable' => ['quantity', 'unit_price', 'subtotal'],
            'relations' => ['quotation', 'product'],
        ],
        'sales-orders' => [
            'model' => SalesOrder::class,
            'searchable' => ['order_number', 'notes'],
            'sortable' => ['order_number', 'order_date', 'status', 'total', 'created_at'],
            'relations' => ['quotation', 'customer', 'items.product'],
        ],
        'sales-order-items' => [
            'model' => SalesOrderItem::class,
            'searchable' => ['description'],
            'sortable' => ['quantity', 'unit_price', 'subtotal'],
            'relations' => ['salesOrder', 'product'],
        ],
        'delivery-orders' => [
            'model' => DeliveryOrder::class,
            'searchable' => ['delivery_number', 'receiver_name', 'notes'],
            'sortable' => ['delivery_number', 'delivery_date', 'received_at', 'status', 'created_at'],
            'relations' => ['salesOrder', 'customer', 'items.product', 'items.salesOrderItem'],
        ],
        'delivery-order-items' => [
            'model' => DeliveryOrderItem::class,
            'searchable' => [],
            'sortable' => ['quantity'],
            'relations' => ['deliveryOrder', 'salesOrderItem', 'product'],
        ],
    ];

    public function __construct(private readonly SalesWorkflowService $salesWorkflow)
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

    public function store(SalesRequest $request, string $resource): JsonResponse
    {
        return $this->storeResource($resource, $request->validated());
    }

    public function show(string $resource, string $id): JsonResponse
    {
        return $this->showResource($resource, $id);
    }

    public function update(SalesRequest $request, string $resource, string $id): JsonResponse
    {
        return $this->updateResource($resource, $id, $request->validated());
    }

    public function destroy(string $resource, string $id): JsonResponse|Response
    {
        return $this->destroyResource($resource, $id);
    }

    public function approveQuotation(ApproveQuotationRequest $request, string $id): JsonResponse
    {
        $salesOrder = $this->salesWorkflow->approveQuotation($id, $request->validated());

        return response()->json([
            'data' => $salesOrder->fresh(['quotation', 'customer', 'items.product']),
        ], 201);
    }

    public function createDeliveryOrder(CreateDeliveryOrderRequest $request, string $id): JsonResponse
    {
        $deliveryOrder = $this->salesWorkflow->createDeliveryOrder($id, $request->validated());

        return response()->json([
            'data' => $deliveryOrder->fresh(['salesOrder', 'customer', 'items.product', 'items.salesOrderItem']),
        ], 201);
    }

    public function shipDeliveryOrder(ShipDeliveryOrderRequest $request, string $id): JsonResponse
    {
        $deliveryOrder = $this->salesWorkflow->shipDeliveryOrder($id, $request->validated());

        return response()->json([
            'data' => $deliveryOrder->fresh(['salesOrder', 'customer', 'items.product', 'items.salesOrderItem']),
        ]);
    }

    protected function filterableColumns(): array
    {
        return [
            'customer_id',
            'quotation_id',
            'sales_order_id',
            'delivery_order_id',
            'product_id',
            'status',
        ];
    }
}
