<?php

namespace App\Services;

use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\SupplierPayable;
use App\Models\ProductReturn;
use App\Models\StorageLocation;
use App\Models\Invoice;
use App\Models\SalesOrderItem;
use App\Models\PurchaseOrderItem;
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
                    if (!isset($itemQuantities[$item->id]) || $itemQuantities[$item->id] <= 0) {
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

                $stock = ProductStock::query()->firstOrNew([
                    'product_id' => $item->product_id,
                    'location_id' => $attributes['to_location_id'],
                ]);

                $stock->quantity = (float) ($stock->quantity ?? 0) + $qtyToReceive;
                $stock->save();

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
                    // Customer Return (Internal Defect): Item goes back to Warehouse
                    $stock = ProductStock::query()->firstOrNew([
                        'product_id' => $item->product_id,
                        'location_id' => $locationId,
                    ]);
                    $stock->quantity = (float)($stock->quantity ?? 0) + $item->quantity;
                    $stock->save();

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

                    if ($return->sales_order_id) {
                        $invoice = Invoice::where('sales_order_id', $return->sales_order_id)->first();
                        if ($invoice) {
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
                    }

                } else if ($return->type === 'supplier') {
                    // Supplier Return: Item leaves Warehouse
                    $qtyToCut = $item->quantity;
                    $stocks = ProductStock::query()
                        ->where('product_id', $item->product_id)
                        ->where('quantity', '>', 0)
                        ->lockForUpdate()
                        ->get();

                    foreach ($stocks as $stock) {
                        if ($qtyToCut <= 0) break;

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

                    if ($return->purchase_order_id) {
                        $payable = SupplierPayable::where('purchase_order_id', $return->purchase_order_id)->first();
                        if ($payable) {
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
                    }
                }
            }

            return $return;
        });
    }
}
