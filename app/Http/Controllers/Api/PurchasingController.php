<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\PurchasingRequest;
use App\Http\Requests\Api\ReceivePurchaseOrderRequest;
use App\Models\GoodsReceiptNote;
use App\Models\ProductReturn;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\ReturnItem;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\SupplierPayable;
use App\Services\PurchasingWorkflowService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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

    public function __construct(private readonly PurchasingWorkflowService $purchasingWorkflow) {}

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
        if ($resource === 'goods-receipt-notes') {
            $receipt = $this->purchasingWorkflow->processGoodsReceipt($request->validated());

            return response()->json([
                'data' => $receipt->fresh(self::RESOURCES[$resource]['relations'] ?? []),
            ], 201);
        }

        if ($resource === 'purchase-orders') {
            $attributes = $request->validated();

            if (! empty($attributes['rfq_id'])) {
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
        if ($resource === 'returns' && $request->has('qc_status')) {
            $status = $request->input('qc_status');
            $return = ProductReturn::findOrFail($id);

            if ($status === 'approved' && $return->qc_status !== 'approved') {
                $updatedReturn = $this->purchasingWorkflow->approveReturn($id);

                return (new JsonResource($updatedReturn->fresh($this->resources()['returns']['relations'] ?? [])))->response();
            } elseif ($status === 'supplier_claim' && $return->qc_status !== 'supplier_claim') {
                $updatedReturn = $this->purchasingWorkflow->claimToSupplier($id);

                return (new JsonResource($updatedReturn->fresh($this->resources()['returns']['relations'] ?? [])))->response();
            }
        }

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
}
