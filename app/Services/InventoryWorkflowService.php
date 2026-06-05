<?php

namespace App\Services;

use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\StockOpnameItem;
use App\Models\StockOpnameSession;
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
}
