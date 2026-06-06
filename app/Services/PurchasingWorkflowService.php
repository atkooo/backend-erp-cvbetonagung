<?php

namespace App\Services;

use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\SupplierPayable;
use Illuminate\Support\Facades\DB;

class PurchasingWorkflowService
{
    /**
     * @param array<string, mixed> $attributes
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

            foreach ($purchaseOrder->items as $item) {
                $remainingQty = (float) $item->quantity - (float) $item->received_qty;

                if ($remainingQty <= 0) {
                    continue;
                }

                $stock = ProductStock::query()->firstOrNew([
                    'product_id' => $item->product_id,
                    'location_id' => $attributes['to_location_id'],
                ]);

                $stock->quantity = (float) ($stock->quantity ?? 0) + $remainingQty;
                $stock->save();

                $item->forceFill(['received_qty' => $item->quantity])->save();

                StockMovement::query()->create([
                    'product_id' => $item->product_id,
                    'from_location_id' => null,
                    'to_location_id' => $attributes['to_location_id'],
                    'type' => 'in',
                    'quantity' => $remainingQty,
                    'reference_type' => 'purchase_order',
                    'reference_id' => $purchaseOrder->id,
                    'reference_number' => $purchaseOrder->po_number,
                    'handled_by' => $attributes['handled_by'] ?? null,
                    'notes' => $attributes['notes'] ?? null,
                    'movement_at' => $attributes['movement_at'],
                ]);
            }

            $purchaseOrder->forceFill([
                'status' => $this->purchaseOrderStatusFor($purchaseOrder->items()->get()),
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
                        'payable_number' => 'AP-' . date('Ymd') . '-' . rand(1000, 9999),
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
}
