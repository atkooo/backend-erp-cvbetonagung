<?php

namespace App\Services\Reports;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class GetLowStockReportAction
{
    /**
     * Generate Low Stock Alert Report dataset for items requiring reorder/restock.
     *
     * @param  array{
     *     category_id?: string|null,
     *     stock_status?: string|null,
     *     search?: string|null
     * }  $filters
     * @return array{
     *     summary: array{
     *         total_low_stock_items: int,
     *         low_stock_count: int,
     *         out_of_stock_count: int,
     *         total_estimated_reorder_cost: float
     *     },
     *     rows: array<int, array<string, mixed>>
     * }
     */
    public function execute(array $filters = []): array
    {
        $query = Product::query()
            ->with(['category', 'unit', 'stocks']);

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('sku')->get();

        $rows = [];
        $lowStockCount = 0;
        $outOfStockCount = 0;
        $totalEstimatedReorderCost = 0.0;

        foreach ($products as $p) {
            $totalStock = (float) $p->stocks->sum('quantity');
            $minStock = (float) $p->min_stock;

            $stockStatus = 'aman';
            if ($totalStock <= 0) {
                $stockStatus = 'habis';
            } elseif ($totalStock <= $minStock) {
                $stockStatus = 'menipis';
            }

            // Skip items that are safe unless filtered specifically
            if (! empty($filters['stock_status'])) {
                if ($stockStatus !== $filters['stock_status']) {
                    continue;
                }
            } else {
                if ($stockStatus === 'aman') {
                    continue;
                }
            }

            if ($stockStatus === 'habis') {
                $outOfStockCount++;
            } elseif ($stockStatus === 'menipis') {
                $lowStockCount++;
            }

            $deficitQty = max(0, $minStock - $totalStock);
            $suggestedReorderQty = $deficitQty > 0 ? $deficitQty : 1;
            $costPrice = (float) $p->cost_price;
            $reorderCost = $suggestedReorderQty * $costPrice;

            $totalEstimatedReorderCost += $reorderCost;

            $rows[] = [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'type' => $p->type,
                'category_name' => $p->category?->name ?: '-',
                'unit_name' => $p->unit?->name ?: '-',
                'unit_code' => $p->unit?->code ?: '-',
                'total_stock' => $totalStock,
                'min_stock' => $minStock,
                'deficit_qty' => $deficitQty,
                'suggested_reorder_qty' => $suggestedReorderQty,
                'cost_price' => $costPrice,
                'estimated_reorder_cost' => round($reorderCost, 2),
                'stock_status' => $stockStatus,
                'stock_status_label' => match ($stockStatus) {
                    'habis' => 'Stok Habis',
                    'menipis' => 'Stok Menipis',
                    default => 'Aman',
                },
            ];
        }

        return [
            'summary' => [
                'total_low_stock_items' => count($rows),
                'low_stock_count' => $lowStockCount,
                'out_of_stock_count' => $outOfStockCount,
                'total_estimated_reorder_cost' => round($totalEstimatedReorderCost, 2),
            ],
            'rows' => $rows,
        ];
    }
}
