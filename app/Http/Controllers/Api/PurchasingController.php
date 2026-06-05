<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\PurchasingRequest;
use App\Http\Requests\Api\ReceivePurchaseOrderRequest;
use App\Models\ProductReturn;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReturnItem;
use App\Models\StockMovement;
use App\Models\SupplierPayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class PurchasingController extends ApiResourceController
{
    /**
     * @var array<string, array{model: class-string<Model>, searchable: array<int, string>, sortable: array<int, string>, relations?: array<int, string>}>
     */
    private const RESOURCES = [
        'purchase-orders' => [
            'model' => PurchaseOrder::class,
            'searchable' => ['po_number', 'notes'],
            'sortable' => ['po_number', 'po_date', 'status', 'total', 'created_at'],
            'relations' => ['supplier', 'items.product', 'supplierPayables'],
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
        $validated = $request->validated();

        $purchaseOrder = DB::transaction(function () use ($id, $validated): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::query()
                ->with('items')
                ->lockForUpdate()
                ->whereKey($id)
                ->firstOrFail();

            abort_if($purchaseOrder->items->isEmpty(), 422, 'Purchase order must have at least one item before receiving.');
            abort_if($purchaseOrder->status === 'cancelled', 422, 'Cancelled purchase order cannot be received.');
            abort_if($purchaseOrder->status === 'fully_received', 409, 'Purchase order has already been fully received.');

            foreach ($purchaseOrder->items as $item) {
                $remainingQty = (float) $item->quantity - (float) $item->received_qty;

                if ($remainingQty <= 0) {
                    continue;
                }

                $stock = ProductStock::query()->firstOrNew([
                    'product_id' => $item->product_id,
                    'location_id' => $validated['to_location_id'],
                ]);

                $stock->quantity = (float) ($stock->quantity ?? 0) + $remainingQty;
                $stock->save();

                $item->forceFill(['received_qty' => $item->quantity])->save();

                StockMovement::query()->create([
                    'product_id' => $item->product_id,
                    'from_location_id' => null,
                    'to_location_id' => $validated['to_location_id'],
                    'type' => 'in',
                    'quantity' => $remainingQty,
                    'reference_type' => 'purchase_order',
                    'reference_id' => $purchaseOrder->id,
                    'reference_number' => $purchaseOrder->po_number,
                    'handled_by' => $validated['handled_by'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'movement_at' => $validated['movement_at'],
                ]);
            }

            $purchaseOrder->forceFill([
                'status' => $this->purchaseOrderStatusFor($purchaseOrder->items()->get()),
            ])->save();

            return $purchaseOrder;
        });

        return response()->json([
            'data' => $purchaseOrder->fresh(['supplier', 'items.product', 'supplierPayables']),
        ]);
    }

    private function purchaseOrderStatusFor($items): string
    {
        $totalQty = 0.0;
        $receivedQty = 0.0;

        foreach ($items as $item) {
            $totalQty += (float) $item->quantity;
            $receivedQty += (float) $item->received_qty;
        }

        if ($receivedQty <= 0) {
            return 'ordered';
        }

        return $receivedQty >= $totalQty ? 'fully_received' : 'partially_received';
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
        ];
    }
}
