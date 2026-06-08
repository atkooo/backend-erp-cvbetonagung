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

    public function store(SalesRequest $request, string $resource): JsonResponse
    {
        if ($resource === 'quotations' || $resource === 'sales-orders') {
            return $this->storeWithItems($resource, $request->validated());
        }

        return $this->storeResource($resource, $request->validated());
    }

    public function show(string $resource, string $id): JsonResponse
    {
        return $this->showResource($resource, $id);
    }

    public function update(SalesRequest $request, string $resource, string $id): JsonResponse
    {
        if ($resource === 'quotations' || $resource === 'sales-orders') {
            return $this->updateWithItems($resource, $id, $request->validated());
        }

        return $this->updateResource($resource, $id, $request->validated());
    }

    protected function storeWithItems(string $resource, array $attributes): JsonResponse
    {
        $config = $this->resourceConfig($resource);
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
        $modelClass = $config['model'];

        $hasItems = array_key_exists('items', $attributes);
        $items = $attributes['items'] ?? [];
        unset($attributes['items']);

        $model = DB::transaction(function () use ($modelClass, $attributes, $items, $resource, $hasItems) {
            /** @var \Illuminate\Database\Eloquent\Model $model */
            $model = $modelClass::query()->create($attributes);

            if ($resource === 'sales-orders' && !empty($attributes['quotation_id'])) {
                $quotation = Quotation::query()->with('items')->find($attributes['quotation_id']);
                if ($quotation) {
                    $quotation->forceFill(['status' => 'approved'])->save();

                    if (!$hasItems || empty($items)) {
                        $subtotal = 0;
                        foreach ($quotation->items as $qItem) {
                            $model->items()->create([
                                'product_id' => $qItem->product_id,
                                'description' => $qItem->description,
                                'quantity' => $qItem->quantity,
                                'unit_price' => $qItem->unit_price,
                                'subtotal' => $qItem->subtotal,
                            ]);
                            $subtotal += $qItem->subtotal;
                        }
                        $model->forceFill([
                            'total' => $subtotal,
                        ])->save();

                        return $model;
                    }
                }
            }

            if ($hasItems) {
                $subtotal = 0;
                foreach ($items as $itemData) {
                    $itemSubtotal = ($itemData['quantity'] ?? 0) * ($itemData['unit_price'] ?? 0);
                    $subtotal += $itemSubtotal;

                    $model->items()->create([
                        'product_id' => $itemData['product_id'],
                        'description' => $itemData['description'] ?? null,
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'subtotal' => $itemSubtotal,
                    ]);
                }

                if ($resource === 'quotations') {
                    $taxAmount = $attributes['tax_amount'] ?? 0;
                    $model->forceFill([
                        'subtotal' => $subtotal,
                        'tax_amount' => $taxAmount,
                        'total' => $subtotal + $taxAmount,
                    ])->save();
                } else {
                    $model->forceFill([
                        'total' => $subtotal,
                    ])->save();
                }
            }

            return $model;
        });

        return response()->json(['data' => $model->fresh($config['relations'] ?? [])], 201);
    }

    protected function updateWithItems(string $resource, string $id, array $attributes): JsonResponse
    {
        $config = $this->resourceConfig($resource);
        $model = $this->findResourceModel($config, $id);

        $hasItems = array_key_exists('items', $attributes);
        $items = $attributes['items'] ?? null;
        unset($attributes['items']);

        DB::transaction(function () use ($model, $attributes, $items, $resource, $hasItems) {
            $model->fill($attributes);
            $model->save();

            if ($hasItems && $items !== null) {
                $model->items()->delete();

                $subtotal = 0;
                foreach ($items as $itemData) {
                    $itemSubtotal = ($itemData['quantity'] ?? 0) * ($itemData['unit_price'] ?? 0);
                    $subtotal += $itemSubtotal;

                    $model->items()->create([
                        'product_id' => $itemData['product_id'],
                        'description' => $itemData['description'] ?? null,
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'subtotal' => $itemSubtotal,
                    ]);
                }

                if ($resource === 'quotations') {
                    $taxAmount = $attributes['tax_amount'] ?? $model->tax_amount ?? 0;
                    $model->forceFill([
                        'subtotal' => $subtotal,
                        'tax_amount' => $taxAmount,
                        'total' => $subtotal + $taxAmount,
                    ])->save();
                } else {
                    $model->forceFill([
                        'total' => $subtotal,
                    ])->save();
                }
            }
        });

        return response()->json(['data' => $model->fresh($config['relations'] ?? [])]);
    }

    public function destroy(string $resource, string $id): JsonResponse|Response
    {
        return $this->destroyResource($resource, $id);
    }

    // Workflow actions (using SalesWorkflowService)
    public function approveQuotation(ApproveQuotationRequest $request, string $id, SalesWorkflowService $service): JsonResponse
    {
        $salesOrder = $service->approveQuotation($id, $request->validated());
        $config = $this->resourceConfig('sales-orders');

        return response()->json(['data' => $salesOrder->fresh($config['relations'] ?? [])], 201);
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
