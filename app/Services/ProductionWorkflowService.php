<?php

namespace App\Services;

use App\Models\Bom;
use App\Models\ProductionWorkOrder;
use App\Models\ProductStock;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class ProductionWorkflowService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function receiveWorkOrder(string $id, array $attributes): ProductionWorkOrder
    {
        return DB::transaction(function () use ($id, $attributes): ProductionWorkOrder {
            $workOrder = ProductionWorkOrder::query()
                ->lockForUpdate()
                ->whereKey($id)
                ->firstOrFail();

            $qtyToReceive = (float) $attributes['quantity'];
            abort_if($qtyToReceive <= 0, 422, 'Quantity to receive must be greater than zero.');

            // Update completed_qty
            $workOrder->completed_qty = (float) $workOrder->completed_qty + $qtyToReceive;

            // Recalculate progress based on target_qty
            if ((float) $workOrder->target_qty > 0) {
                $progress = round(($workOrder->completed_qty / (float) $workOrder->target_qty) * 100);
                $workOrder->progress = min(100, (int) $progress);
            }

            $workOrder->save();

            // 1. Add finished product to inventory
            $stock = ProductStock::query()->firstOrNew([
                'product_id' => $workOrder->product_id,
                'location_id' => $attributes['target_location_id'],
            ]);
            $stock->quantity = (float) ($stock->quantity ?? 0) + $qtyToReceive;
            $stock->save();

            StockMovement::query()->create([
                'product_id' => $workOrder->product_id,
                'from_location_id' => null,
                'to_location_id' => $attributes['target_location_id'],
                'type' => 'in',
                'quantity' => $qtyToReceive,
                'reference_type' => 'production_work_order',
                'reference_id' => $workOrder->id,
                'reference_number' => $workOrder->work_order_number,
                'handled_by' => $attributes['handled_by'] ?? null,
                'notes' => 'Penerimaan Hasil Produksi'.(isset($attributes['notes']) ? ': '.$attributes['notes'] : ''),
                'movement_at' => $attributes['movement_at'] ?? now()->toDateTimeString(),
            ]);

            // 2. Deduct raw materials based on active BOM
            $bom = Bom::query()
                ->with('items')
                ->where('product_id', $workOrder->product_id)
                ->where('status', 'active')
                ->first();

            if ($bom && isset($attributes['source_location_id'])) {
                foreach ($bom->items as $bomItem) {
                    if (! $bomItem->component_product_id) {
                        continue;
                    }

                    // Calculate quantity to deduct based on BOM ratio
                    // BOM quantity is typically per 1 unit of finished product
                    $qtyToDeduct = (float) $bomItem->quantity * $qtyToReceive;

                    if ($qtyToDeduct <= 0) {
                        continue;
                    }

                    $matStock = ProductStock::query()->firstOrNew([
                        'product_id' => $bomItem->component_product_id,
                        'location_id' => $attributes['source_location_id'],
                    ]);
                    $matStock->quantity = (float) ($matStock->quantity ?? 0) - $qtyToDeduct;
                    $matStock->save();

                    StockMovement::query()->create([
                        'product_id' => $bomItem->component_product_id,
                        'from_location_id' => $attributes['source_location_id'],
                        'to_location_id' => null,
                        'type' => 'out',
                        'quantity' => $qtyToDeduct,
                        'reference_type' => 'production_work_order',
                        'reference_id' => $workOrder->id,
                        'reference_number' => $workOrder->work_order_number,
                        'handled_by' => $attributes['handled_by'] ?? null,
                        'notes' => 'Penggunaan Material Produksi WO: '.$workOrder->work_order_number,
                        'movement_at' => $attributes['movement_at'] ?? now()->toDateTimeString(),
                    ]);
                }
            }

            return $workOrder;
        });
    }
}
