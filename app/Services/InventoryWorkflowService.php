<?php

namespace App\Services;

use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\StockOpnameItem;
use App\Models\StockOpnameSession;
use App\Models\Bag;
use App\Models\BagItem;
use Illuminate\Support\Facades\DB;

class InventoryWorkflowService
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function adjustStockOpnameItem(string $id, array $attributes): StockOpnameItem
    {
        return DB::transaction(function () use ($id, $attributes): StockOpnameItem {
            $item = StockOpnameItem::query()
                ->with(['approvalRequest', 'session'])
                ->lockForUpdate()
                ->whereKey($id)
                ->firstOrFail();

            abort_if((float) $item->difference_qty === 0.0, 422, 'Stock opname item has no difference to adjust.');
            abort_if($item->approvalRequest === null, 422, 'Stock opname adjustment requires an approval request.');
            abort_if($item->approvalRequest->status !== 'approved', 422, 'Stock opname approval request must be approved before adjustment.');
            abort_if(
                StockMovement::query()
                    ->where('reference_type', 'stock_opname_item')
                    ->where('reference_id', $item->id)
                    ->exists(),
                409,
                'Stock opname item has already been adjusted.',
            );

            $stock = ProductStock::query()->firstOrNew([
                'product_id' => $item->product_id,
                'location_id' => $item->location_id,
            ]);
            $stock->quantity = $item->physical_qty;
            $stock->save();

            $difference = (float) $item->difference_qty;

            StockMovement::query()->create([
                'product_id' => $item->product_id,
                'from_location_id' => $difference < 0 ? $item->location_id : null,
                'to_location_id' => $difference > 0 ? $item->location_id : null,
                'type' => 'adjustment',
                'quantity' => abs($difference),
                'reference_type' => 'stock_opname_item',
                'reference_id' => $item->id,
                'reference_number' => $item->session?->opname_number,
                'handled_by' => $attributes['handled_by'] ?? null,
                'notes' => $attributes['notes'] ?? $item->notes,
                'movement_at' => $attributes['movement_at'],
            ]);

            $this->closeSessionIfFullyAdjusted($item->session_id);

            return $item;
        });
    }

    private function closeSessionIfFullyAdjusted(string $sessionId): void
    {
        $session = StockOpnameSession::query()
            ->with('items')
            ->lockForUpdate()
            ->whereKey($sessionId)
            ->first();

        if ($session === null) {
            return;
        }

        $allAdjusted = $session->items->every(function (StockOpnameItem $item): bool {
            if ((float) $item->difference_qty === 0.0) {
                return true;
            }

            return StockMovement::query()
                ->where('reference_type', 'stock_opname_item')
                ->where('reference_id', $item->id)
                ->exists();
        });

        if ($allAdjusted) {
            $session->forceFill([
                'status' => 'closed',
                'closed_at' => now(),
            ])->save();
        }
    }

    public function processBag(array $attributes): Bag
    {
        return DB::transaction(function () use ($attributes): Bag {
            $bag = Bag::create([
                'date' => $attributes['date'] ?? now(),
                'warehouse_id' => $attributes['warehouse_id'],
                'location_id' => $attributes['location_id'] ?? null,
                'type' => $attributes['type'],
                'notes' => $attributes['notes'] ?? null,
                'status' => 'Final',
                'created_by' => $attributes['created_by'] ?? null,
            ]);

            $items = $attributes['items'] ?? [];
            foreach ($items as $itemData) {
                $bagItem = $bag->items()->create([
                    'product_id' => $itemData['product_id'],
                    'quantity' => $itemData['quantity'],
                    'notes' => $itemData['notes'] ?? null,
                ]);

                $stock = ProductStock::query()->firstOrNew([
                    'product_id' => $bagItem->product_id,
                    'location_id' => $bag->location_id,
                ]);

                $qty = (float) $bagItem->quantity;

                if ($bag->type === 'in') {
                    $stock->quantity = ((float) $stock->quantity) + $qty;
                } elseif ($bag->type === 'out') {
                    $stock->quantity = ((float) $stock->quantity) - $qty;
                } else {
                    $stock->quantity = $qty; // adjustment is treated as absolute set
                }
                $stock->save();

                StockMovement::query()->create([
                    'product_id' => $bagItem->product_id,
                    'from_location_id' => $bag->type === 'out' ? $bag->location_id : null,
                    'to_location_id' => $bag->type === 'in' ? $bag->location_id : null,
                    'type' => $bag->type,
                    'quantity' => abs($qty),
                    'reference_type' => 'bag',
                    'reference_id' => $bag->id,
                    'reference_number' => $bag->bag_number,
                    'handled_by' => $bag->created_by,
                    'notes' => $bagItem->notes ?? $bag->notes,
                    'movement_at' => $bag->date,
                ]);
            }

            return $bag;
        });
    }
}
