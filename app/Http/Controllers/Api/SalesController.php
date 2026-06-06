<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SalesRequest;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SalesController extends ApiResourceController
{
    private const RESOURCES = [
        'quotations' => [
            'model' => Quotation::class,
            'searchable' => ['quotation_number', 'notes'],
            'sortable' => ['quotation_number', 'quotation_date', 'valid_until', 'status', 'total'],
            'relations' => ['customer', 'items.product'],
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
            'sortable' => ['order_number', 'order_date', 'status', 'total'],
            'relations' => ['customer', 'quotation', 'items.product', 'deliveryOrders'],
        ],
        'sales-order-items' => [
            'model' => SalesOrderItem::class,
            'searchable' => ['description'],
            'sortable' => ['quantity', 'unit_price', 'delivered_qty', 'subtotal'],
            'relations' => ['salesOrder', 'product'],
        ],
        'delivery-orders' => [
            'model' => DeliveryOrder::class,
            'searchable' => ['delivery_number', 'notes', 'shipping_address'],
            'sortable' => ['delivery_number', 'delivery_date', 'status'],
            'relations' => ['customer', 'salesOrder', 'items.product'],
        ],
        'delivery-order-items' => [
            'model' => DeliveryOrderItem::class,
            'searchable' => ['notes'],
            'sortable' => ['quantity'],
            'relations' => ['deliveryOrder', 'salesOrderItem', 'product'],
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

    public function store(Request $request, string $resource): JsonResponse
    {
        // Validation could be added here
        return $this->storeResource($resource, $request->all());
    }

    public function show(string $resource, string $id): JsonResponse
    {
        return $this->showResource($resource, $id);
    }

    public function update(Request $request, string $resource, string $id): JsonResponse
    {
        return $this->updateResource($resource, $id, $request->all());
    }

    public function destroy(string $resource, string $id): JsonResponse|Response
    {
        return $this->destroyResource($resource, $id);
    }

    // Workflow actions (placeholders for approve, deliver, ship)
    public function approveQuotation(Request $request, string $id): JsonResponse
    {
        $quote = Quotation::findOrFail($id);
        $quote->update(['status' => 'Disetujui']);
        return response()->json(['message' => 'Quotation approved', 'data' => $quote]);
    }

    public function createDeliveryOrder(Request $request, string $id): JsonResponse
    {
        $so = SalesOrder::findOrFail($id);
        $so->update(['status' => 'Sebagian Dikirim']);
        return response()->json(['message' => 'Delivery order created for SO', 'data' => $so]);
    }

    public function shipDeliveryOrder(Request $request, string $id): JsonResponse
    {
        $do = DeliveryOrder::findOrFail($id);
        $do->update(['status' => 'Terkirim']);
        return response()->json(['message' => 'Delivery order shipped', 'data' => $do]);
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
