<?php

namespace App\Services;

use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\SupplierPayable;
use Illuminate\Support\Facades\DB;

class PurchasingWorkflowService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function receivePurchaseOrder(string $id, array $attributes): PurchaseOrder
    {
        return DB::transaction(function () use ($id, $attributes): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::query()
                ->with('items')
                ->lockForUpdate()
                ->whereKey($id)
                ->firstOrFail();

            abort_if($purchaseOrder->items->isEmpty(), 422, 'Purchase order must have at least one item before receiving.');
            abort_if($purchaseOrder->status === 'cancelled', 422, 'Cancelled purchase order cannot be received.');
            abort_if($purchaseOrder->status === 'fully_received', 409, 'Purchase order has already been fully received.');

            $itemQuantities = [];
            if (isset($attributes['items'])) {
                foreach ($attributes['items'] as $reqItem) {
                    $itemQuantities[$reqItem['id']] = (float) $reqItem['quantity'];
                }
            }

            foreach ($purchaseOrder->items as $item) {
                $remainingQty = (float) $item->quantity - (float) $item->received_qty;

                $qtyToReceive = $remainingQty;
                if (isset($attributes['items'])) {
                    if (! isset($itemQuantities[$item->id]) || $itemQuantities[$item->id] <= 0) {
                        continue;
                    }
                    $qtyToReceive = $itemQuantities[$item->id];
                }

                if ($qtyToReceive > $remainingQty) {
                    $qtyToReceive = $remainingQty;
                }

                if ($qtyToReceive <= 0) {
                    continue;
                }

                $stock = ProductStock::query()->firstOrCreate(
                    ['product_id' => $item->product_id, 'location_id' => $attributes['to_location_id']],
                    ['quantity' => 0]
                );

                $stock->increment('quantity', $qtyToReceive);

                $item->forceFill(['received_qty' => (float) $item->received_qty + $qtyToReceive])->save();

                StockMovement::query()->create([
                    'product_id' => $item->product_id,
                    'from_location_id' => null,
                    'to_location_id' => $attributes['to_location_id'],
                    'type' => 'in',
                    'quantity' => $qtyToReceive,
                    'reference_type' => 'purchase_order',
                    'reference_id' => $purchaseOrder->id,
                    'reference_number' => $purchaseOrder->po_number,
                    'handled_by' => $attributes['handled_by'] ?? null,
                    'notes' => $attributes['notes'] ?? null,
                    'movement_at' => $attributes['movement_at'],
                ]);
            }

            $purchaseOrder->forceFill([
                'status' => $this->resolvePoReceiptStatus($purchaseOrder->items()->get()),
            ])->save();

            // Create Supplier Payable if fully or partially received
            if ($purchaseOrder->status === 'fully_received' || $purchaseOrder->status === 'partially_received') {
                $payableAmount = 0.0;
                foreach ($purchaseOrder->items as $item) {
                    $payableAmount += ($item->received_qty * $item->unit_price);
                }

                // Delete existing payable for this PO to regenerate (simplistic approach for now)
                SupplierPayable::query()->where('purchase_order_id', $purchaseOrder->id)->delete();

                if ($payableAmount > 0) {
                    SupplierPayable::query()->create([
                        'supplier_id' => $purchaseOrder->supplier_id,
                        'purchase_order_id' => $purchaseOrder->id,
                        'payable_number' => 'AP-'.date('Ymd').'-'.rand(1000, 9999),
                        'amount' => $payableAmount,
                        'paid_amount' => 0,
                        'due_date' => now()->addDays(30), // Default due date
                        'status' => 'open',
                    ]);
                }
            }

            return $purchaseOrder;
        });
    }

    /**
     * Hitung status PO berdasarkan total vs received quantity.
     *
     * @param  iterable<PurchaseOrderItem>  $items
     */
    private function resolvePoReceiptStatus(iterable $items): string
    {
        $totalQty = 0.0;
        $receivedQty = 0.0;

        foreach ($items as $item) {
            $totalQty += (float) $item->quantity;
            $receivedQty += (float) $item->received_qty;
        }

        return match (true) {
            $receivedQty <= 0 => 'ordered',
            $receivedQty >= $totalQty => 'fully_received',
            default => 'partially_received',
        };
    }

    // Return logic has been moved to ReturnWorkflowService

    public function processGoodsReceipt(array $attributes): GoodsReceiptNote
    {
        $items = $attributes['items'] ?? [];
        unset($attributes['items']);

        return DB::transaction(function () use ($attributes, $items): GoodsReceiptNote {
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
                    $stock = ProductStock::query()->firstOrCreate(
                        ['product_id' => $receiptItem->product_id, 'location_id' => $receipt->to_location_id],
                        ['quantity' => 0]
                    );
                    $stock->increment('quantity', $receivedQty);

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

            return $receipt;
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

        $purchaseOrder->forceFill(['status' => $this->resolvePoReceiptStatus($purchaseOrder->items)])->save();
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
            'status' => SupplierPayable::resolveStatus($paidAmount, $payableAmount),
        ];

        if ($payable === null) {
            $attributes['payable_number'] = 'AP-'.$purchaseOrder->po_number;
            SupplierPayable::query()->create($attributes);

            return;
        }

        $payable->forceFill($attributes)->save();
    }
}
