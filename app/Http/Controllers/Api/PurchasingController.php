<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\PurchasingRequest;
use App\Http\Requests\Api\ReceivePurchaseOrderRequest;
use App\Models\ProductReturn;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReturnItem;
use App\Models\SupplierPayable;
use App\Services\PurchasingWorkflowService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\ProductStock;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class PurchasingController extends ApiResourceController
{
    /**
     * @var array<string, array{model: class-string<Model>, searchable: array<int, string>, sortable: array<int, string>, relations?: array<int, string>}>
     */
    private const RESOURCES = [
        'purchase-requests' => [
            'model' => PurchaseRequest::class,
            'searchable' => ['pr_number', 'notes', 'department'],
            'sortable' => ['pr_number', 'request_date', 'required_date', 'status'],
            'relations' => ['requester', 'items.product', 'items.product.unit', 'purchaseOrders'],
        ],
        'purchase-request-items' => [
            'model' => PurchaseRequestItem::class,
            'searchable' => ['description'],
            'sortable' => ['quantity', 'status'],
            'relations' => ['purchaseRequest', 'product'],
        ],
        'rfqs' => [
            'model' => Rfq::class,
            'searchable' => ['rfq_number', 'notes'],
            'sortable' => ['rfq_number', 'rfq_date', 'valid_until', 'status'],
            'relations' => ['purchaseRequest', 'supplier', 'items.product', 'items.product.unit', 'purchaseOrders'],
        ],
        'rfq-items' => [
            'model' => RfqItem::class,
            'searchable' => ['description'],
            'sortable' => ['quantity', 'quoted_unit_price'],
            'relations' => ['rfq', 'product'],
        ],
        'goods-receipts' => [
            'model' => GoodsReceiptNote::class,
            'searchable' => ['grn_number', 'delivery_order_number', 'notes'],
            'sortable' => ['grn_number', 'receipt_date', 'status'],
            'relations' => ['purchaseOrder', 'warehouse', 'toLocation', 'receiver', 'items.product', 'items.product.unit'],
        ],
        'goods-receipt-items' => [
            'model' => GoodsReceiptNoteItem::class,
            'searchable' => ['notes'],
            'sortable' => ['received_qty', 'rejected_qty'],
            'relations' => ['goodsReceiptNote', 'purchaseOrderItem', 'product'],
        ],
        'goods-receipt-notes' => [
            'model' => GoodsReceiptNote::class,
            'searchable' => ['grn_number', 'delivery_order_number', 'notes'],
            'sortable' => ['grn_number', 'receipt_date', 'status'],
            'relations' => ['purchaseOrder', 'warehouse', 'toLocation', 'receiver', 'items.product', 'items.product.unit'],
        ],
        'goods-receipt-note-items' => [
            'model' => GoodsReceiptNoteItem::class,
            'searchable' => ['notes'],
            'sortable' => ['received_qty', 'rejected_qty'],
            'relations' => ['goodsReceiptNote', 'purchaseOrderItem', 'product'],
        ],
        'purchase-orders' => [
            'model' => PurchaseOrder::class,
            'searchable' => ['po_number', 'notes'],
            'sortable' => ['po_number', 'po_date', 'status', 'total', 'created_at'],
            'relations' => ['supplier', 'items.product', 'items.product.unit', 'supplierPayables'],
        ],
        'purchase-order-items' => [
            'model' => PurchaseOrderItem::class,
            'searchable' => ['description'],
            'sortable' => ['quantity', 'unit_price', 'received_qty', 'subtotal'],
            'relations' => ['purchaseOrder', 'product'],
        ],
        'supplier-payables' => [
            'model' => SupplierPayable::class,
            'searchable' => ['payable_number'],
            'sortable' => ['payable_number', 'due_date', 'amount', 'paid_amount', 'status', 'created_at'],
            'relations' => ['purchaseOrder', 'supplier'],
        ],
        'returns' => [
            'model' => ProductReturn::class,
            'searchable' => ['return_number', 'reason', 'qc_status'],
            'sortable' => ['return_number', 'type', 'qc_status', 'created_at'],
            'relations' => ['customer', 'supplier', 'salesOrder', 'purchaseOrder', 'createdBy', 'items.product'],
        ],
        'return-items' => [
            'model' => ReturnItem::class,
            'searchable' => ['notes'],
            'sortable' => ['quantity'],
            'relations' => ['productReturn', 'product'],
        ],
    ];

    public function __construct(private readonly PurchasingWorkflowService $purchasingWorkflow)
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

    public function store(PurchasingRequest $request, string $resource): JsonResponse
    {
        if ($resource === 'goods-receipts' || $resource === 'goods-receipt-notes') {
            return $this->storeGoodsReceipt($request->validated(), $resource);
        }

        if ($resource === 'purchase-orders') {
            $attributes = $request->validated();

            if (!empty($attributes['rfq_id'])) {
                $existingPurchaseOrder = PurchaseOrder::query()
                    ->where('rfq_id', $attributes['rfq_id'])
                    ->first();

                if ($existingPurchaseOrder !== null) {
                    return response()->json([
                        'message' => 'Purchase order for this RFQ already exists.',
                        'data' => $existingPurchaseOrder->fresh(self::RESOURCES['purchase-orders']['relations'] ?? []),
                    ], 409);
                }
            }

            return $this->storeResource($resource, $attributes);
        }

        return $this->storeResource($resource, $request->validated());
    }

    public function show(string $resource, string $id): JsonResponse
    {
        return $this->showResource($resource, $id);
    }

    public function update(PurchasingRequest $request, string $resource, string $id): JsonResponse
    {
        return $this->updateResource($resource, $id, $request->validated());
    }

    public function destroy(string $resource, string $id): JsonResponse|Response
    {
        return $this->destroyResource($resource, $id);
    }

    public function receivePurchaseOrder(ReceivePurchaseOrderRequest $request, string $id): JsonResponse
    {
        $purchaseOrder = $this->purchasingWorkflow->receivePurchaseOrder($id, $request->validated());

        return response()->json([
            'data' => $purchaseOrder->fresh(['supplier', 'items.product', 'supplierPayables']),
        ]);
    }

    protected function filterableColumns(): array
    {
        return [
            'supplier_id',
            'purchase_order_id',
            'customer_id',
            'sales_order_id',
            'return_id',
            'product_id',
            'type',
            'status',
            'qc_status',
            'purchase_request_id',
            'rfq_id',
            'goods_receipt_note_id',
            'warehouse_id',
            'to_location_id',
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function storeGoodsReceipt(array $attributes, string $resource): JsonResponse
    {
        $config = self::RESOURCES[$resource];
        $items = $attributes['items'] ?? [];
        unset($attributes['items']);

        return DB::transaction(function () use ($attributes, $items, $config): JsonResponse {
            $receipt = GoodsReceiptNote::query()->create([
                'purchase_order_id' => $attributes['purchase_order_id'] ?? null,
                'warehouse_id' => $attributes['warehouse_id'] ?? null,
                'to_location_id' => $attributes['to_location_id'] ?? null,
                'received_by' => $attributes['received_by'] ?? null,
                'receipt_date' => $attributes['receipt_date'],
                'delivery_order_number' => $attributes['delivery_order_number'] ?? null,
                'status' => $attributes['status'] ?? 'posted',
                'notes' => $attributes['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $receivedQty = (float) ($item['received_quantity'] ?? $item['received_qty'] ?? 0);
                $rejectedQty = (float) ($item['rejected_quantity'] ?? $item['rejected_qty'] ?? 0);

                if ($receivedQty <= 0 && $rejectedQty <= 0) {
                    continue;
                }

                $receiptItem = GoodsReceiptNoteItem::query()->create([
                    'goods_receipt_note_id' => $receipt->id,
                    'purchase_order_item_id' => $item['purchase_order_item_id'] ?? null,
                    'product_id' => $item['product_id'],
                    'received_qty' => $receivedQty,
                    'rejected_qty' => $rejectedQty,
                    'notes' => $item['notes'] ?? null,
                ]);

                if ($receipt->to_location_id !== null && $receivedQty > 0) {
                    $stock = ProductStock::query()->firstOrNew([
                        'product_id' => $receiptItem->product_id,
                        'location_id' => $receipt->to_location_id,
                    ]);
                    $stock->quantity = (float) ($stock->quantity ?? 0) + $receivedQty;
                    $stock->save();

                    StockMovement::query()->create([
                        'product_id' => $receiptItem->product_id,
                        'from_location_id' => null,
                        'to_location_id' => $receipt->to_location_id,
                        'type' => 'in',
                        'quantity' => $receivedQty,
                        'reference_type' => 'goods_receipt',
                        'reference_id' => $receipt->id,
                        'reference_number' => $receipt->grn_number,
                        'handled_by' => $receipt->received_by,
                        'notes' => $receiptItem->notes ?? $receipt->notes,
                        'movement_at' => $receipt->receipt_date,
                    ]);
                }

                if ($receiptItem->purchaseOrderItem !== null && $receivedQty > 0) {
                    $poItem = $receiptItem->purchaseOrderItem;
                    $poItem->forceFill([
                        'received_qty' => (float) $poItem->received_qty + $receivedQty,
                    ])->save();
                }
            }

            if ($receipt->purchase_order_id !== null) {
                $this->refreshPurchaseOrderReceiptStatus($receipt->purchase_order_id);
                $this->syncSupplierPayableForPurchaseOrder($receipt->purchase_order_id);
            }

            return response()->json([
                'data' => $receipt->fresh($config['relations'] ?? []),
            ], 201);
        });
    }

    private function refreshPurchaseOrderReceiptStatus(string $purchaseOrderId): void
    {
        $purchaseOrder = PurchaseOrder::query()
            ->with('items')
            ->whereKey($purchaseOrderId)
            ->first();

        if ($purchaseOrder === null || $purchaseOrder->items->isEmpty()) {
            return;
        }

        $totalQty = 0.0;
        $receivedQty = 0.0;

        foreach ($purchaseOrder->items as $item) {
            $totalQty += (float) $item->quantity;
            $receivedQty += (float) $item->received_qty;
        }

        $status = match (true) {
            $receivedQty <= 0 => 'ordered',
            $receivedQty >= $totalQty => 'fully_received',
            default => 'partially_received',
        };

        $purchaseOrder->forceFill(['status' => $status])->save();
    }

    private function syncSupplierPayableForPurchaseOrder(string $purchaseOrderId): void
    {
        $purchaseOrder = PurchaseOrder::query()
            ->with('items')
            ->whereKey($purchaseOrderId)
            ->first();

        if ($purchaseOrder === null || $purchaseOrder->items->isEmpty()) {
            return;
        }

        $payableAmount = 0.0;
        foreach ($purchaseOrder->items as $item) {
            $payableAmount += (float) $item->received_qty * (float) $item->unit_price;
        }

        if ($payableAmount <= 0) {
            return;
        }

        $payable = SupplierPayable::query()
            ->where('purchase_order_id', $purchaseOrder->id)
            ->first();

        $paidAmount = (float) ($payable?->paid_amount ?? 0);

        $attributes = [
            'supplier_id' => $purchaseOrder->supplier_id,
            'purchase_order_id' => $purchaseOrder->id,
            'amount' => $payableAmount,
            'paid_amount' => $paidAmount,
            'due_date' => $payable?->due_date ?? now()->addDays(30),
            'status' => $this->supplierPayableStatusFor($paidAmount, $payableAmount),
        ];

        if ($payable === null) {
            $attributes['payable_number'] = 'AP-' . $purchaseOrder->po_number;
            SupplierPayable::query()->create($attributes);
            return;
        }

        $payable->forceFill($attributes)->save();
    }

    private function supplierPayableStatusFor(float $paidAmount, float $amount): string
    {
        if ($paidAmount <= 0) {
            return 'open';
        }

        return $paidAmount >= $amount ? 'paid' : 'partial';
    }
}
