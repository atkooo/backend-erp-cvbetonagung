<?php

namespace App\Services;

use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\Invoice;
use App\Models\ProductReturn;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReturnItem;
use App\Models\SalesOrderItem;
use App\Models\StockMovement;
use App\Models\StorageLocation;
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

    public function approveReturn(string $id): ProductReturn
    {
        return DB::transaction(function () use ($id): ProductReturn {
            $return = ProductReturn::query()->with('items')->findOrFail($id);

            abort_if($return->qc_status === 'approved', 422, 'Return is already approved.');

            $return->qc_status = 'approved';
            $return->save();

            $location = StorageLocation::first();
            $locationId = $location ? $location->id : null;

            foreach ($return->items as $item) {
                if ($return->type === 'customer') {
                    $this->approveCustomerReturnItem($return, $item, $locationId);
                } elseif ($return->type === 'supplier') {
                    $this->approveSupplierReturnItem($return, $item);
                }
            }

            return $return;
        });
    }

    private function approveCustomerReturnItem(ProductReturn $return, ReturnItem $item, ?string $locationId): void
    {
        $stock = ProductStock::query()->firstOrCreate(
            ['product_id' => $item->product_id, 'location_id' => $locationId],
            ['quantity' => 0]
        );
        $stock->increment('quantity', $item->quantity);

        StockMovement::query()->create([
            'product_id' => $item->product_id,
            'from_location_id' => null,
            'to_location_id' => $locationId,
            'type' => 'in',
            'quantity' => $item->quantity,
            'reference_type' => 'return',
            'reference_id' => $return->id,
            'reference_number' => $return->return_number,
            'notes' => 'Customer Return Approved',
            'movement_at' => now(),
        ]);

        if (! $return->sales_order_id) {
            return;
        }

        $invoice = Invoice::where('sales_order_id', $return->sales_order_id)->first();
        if (! $invoice) {
            return;
        }

        $soItem = SalesOrderItem::where('sales_order_id', $return->sales_order_id)
            ->where('product_id', $item->product_id)
            ->first();

        if ($soItem) {
            $deduction = $item->quantity * $soItem->unit_price;
            $invoice->amount = max(0, $invoice->amount - $deduction);
            $invoice->status = $invoice->paid_amount >= $invoice->amount ? 'paid' : 'partial';
            $invoice->save();
        }
    }

    private function approveSupplierReturnItem(ProductReturn $return, ReturnItem $item): void
    {
        $qtyToCut = $item->quantity;
        $stocks = ProductStock::query()
            ->where('product_id', $item->product_id)
            ->where('quantity', '>', 0)
            ->lockForUpdate()
            ->get();

        foreach ($stocks as $stock) {
            if ($qtyToCut <= 0) {
                break;
            }

            $cut = min((float) $stock->quantity, $qtyToCut);
            $stock->quantity = (float) $stock->quantity - $cut;
            $stock->save();

            StockMovement::query()->create([
                'product_id' => $item->product_id,
                'from_location_id' => $stock->location_id,
                'to_location_id' => null,
                'type' => 'out',
                'quantity' => $cut,
                'reference_type' => 'return',
                'reference_id' => $return->id,
                'reference_number' => $return->return_number,
                'notes' => 'Supplier Return Approved',
                'movement_at' => now(),
            ]);

            $qtyToCut -= $cut;
        }

        if (! $return->purchase_order_id) {
            return;
        }

        $payable = SupplierPayable::where('purchase_order_id', $return->purchase_order_id)->first();
        if (! $payable) {
            return;
        }

        $poItem = PurchaseOrderItem::where('purchase_order_id', $return->purchase_order_id)
            ->where('product_id', $item->product_id)
            ->first();

        if ($poItem) {
            $deduction = $item->quantity * $poItem->unit_price;
            $payable->amount = max(0, $payable->amount - $deduction);
            $payable->status = $payable->paid_amount >= $payable->amount ? 'paid' : 'open';
            $payable->save();
        }
    }

    public function claimToSupplier(string $id): ProductReturn
    {
        return DB::transaction(function () use ($id): ProductReturn {
            $customerReturn = ProductReturn::query()->with('items')->findOrFail($id);

            abort_if($customerReturn->type !== 'customer', 422, 'Only customer returns can be claimed to supplier.');
            abort_if($customerReturn->qc_status === 'supplier_claim', 422, 'Return is already claimed to supplier.');

            $poGroups = [];

            foreach ($customerReturn->items as $item) {
                // Find latest PO for this product
                $po = PurchaseOrder::query()
                    ->whereHas('items', function ($query) use ($item) {
                        $query->where('product_id', $item->product_id);
                    })
                    ->latest('created_at')
                    ->first();

                if ($po) {
                    if (! isset($poGroups[$po->id])) {
                        $poGroups[$po->id] = [
                            'supplier_id' => $po->supplier_id,
                            'purchase_order_id' => $po->id,
                            'items' => [],
                        ];
                    }
                    $poGroups[$po->id]['items'][] = clone $item;
                }
            }

            abort_if(empty($poGroups), 422, 'Tidak bisa diklaim. Seluruh barang merupakan produksi internal dan tidak memiliki histori Purchase Order.');

            $customerReturn->qc_status = 'supplier_claim';
            $customerReturn->save();

            foreach ($poGroups as $poId => $group) {
                $supplierReturn = ProductReturn::query()->create([
                    'return_number' => 'RTN-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4)),
                    'type' => 'supplier',
                    'supplier_id' => $group['supplier_id'],
                    'purchase_order_id' => $group['purchase_order_id'],
                    'reason' => 'Otomatis dibuat dari Klaim Pelanggan '.$customerReturn->return_number,
                    'qc_status' => 'pending_qc',
                    'created_by' => auth()->id() ?? $customerReturn->created_by,
                ]);

                foreach ($group['items'] as $item) {
                    ReturnItem::query()->create([
                        'return_id' => $supplierReturn->id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'notes' => $item->notes,
                    ]);
                }
            }

            return $customerReturn;
        });
    }

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
