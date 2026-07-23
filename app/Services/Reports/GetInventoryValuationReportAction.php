<?php

namespace App\Services\Reports;

use App\Models\ProductStock;
use App\Models\Warehouse;

class GetInventoryValuationReportAction
{
    /**
     * Generate Warehouse & Category Stock Valuation Report.
     *
     * @param  array{
     *     warehouse_id?: string|null,
     *     category_id?: string|null
     * }  $filters
     * @return array{
     *     summary: array{
     *         total_warehouses: int,
     *         total_categories: int,
     *         grand_total_qty: float,
     *         grand_total_cogs_value: float,
     *         grand_total_selling_value: float,
     *         grand_potential_profit: float
     *     },
     *     by_warehouse: array<int, array<string, mixed>>,
     *     by_category: array<int, array<string, mixed>>
     * }
     */
    public function execute(array $filters = []): array
    {
        $stocksQuery = ProductStock::query()
            ->with(['product.category', 'location.warehouse']);

        if (! empty($filters['warehouse_id'])) {
            $stocksQuery->whereHas('location', function ($q) use ($filters) {
                $q->where('warehouse_id', $filters['warehouse_id']);
            });
        }

        if (! empty($filters['category_id'])) {
            $stocksQuery->whereHas('product', function ($q) use ($filters) {
                $q->where('category_id', $filters['category_id']);
            });
        }

        $stocks = $stocksQuery->get();

        $warehouseMap = [];
        $categoryMap = [];

        $grandQty = 0.0;
        $grandCogs = 0.0;
        $grandSelling = 0.0;

        foreach ($stocks as $s) {
            $qty = (float) $s->quantity;
            $product = $s->product;
            if (! $product) {
                continue;
            }

            $costPrice = (float) $product->cost_price;
            $sellingPrice = (float) $product->selling_price;

            $cogsVal = $qty * $costPrice;
            $sellingVal = $qty * $sellingPrice;

            $grandQty += $qty;
            $grandCogs += $cogsVal;
            $grandSelling += $sellingVal;

            // Warehouse aggregation
            $wh = $s->location?->warehouse;
            $whId = $wh?->id ?: 'unassigned';
            $whName = $wh?->name ?: 'Gudang Utama / Tanpa Gudang';

            if (! isset($warehouseMap[$whId])) {
                $warehouseMap[$whId] = [
                    'warehouse_id' => $whId,
                    'warehouse_name' => $whName,
                    'total_items' => 0,
                    'total_stock_qty' => 0.0,
                    'total_cogs_value' => 0.0,
                    'total_selling_value' => 0.0,
                ];
            }
            $warehouseMap[$whId]['total_items']++;
            $warehouseMap[$whId]['total_stock_qty'] += $qty;
            $warehouseMap[$whId]['total_cogs_value'] += $cogsVal;
            $warehouseMap[$whId]['total_selling_value'] += $sellingVal;

            // Category aggregation
            $cat = $product->category;
            $catId = $cat?->id ?: 'uncategorized';
            $catName = $cat?->name ?: 'Tanpa Kategori';

            if (! isset($categoryMap[$catId])) {
                $categoryMap[$catId] = [
                    'category_id' => $catId,
                    'category_name' => $catName,
                    'total_items' => 0,
                    'total_stock_qty' => 0.0,
                    'total_cogs_value' => 0.0,
                    'total_selling_value' => 0.0,
                ];
            }
            $categoryMap[$catId]['total_items']++;
            $categoryMap[$catId]['total_stock_qty'] += $qty;
            $categoryMap[$catId]['total_cogs_value'] += $cogsVal;
            $categoryMap[$catId]['total_selling_value'] += $sellingVal;
        }

        $byWarehouse = array_values(array_map(function ($wh) {
            $wh['potential_profit'] = $wh['total_selling_value'] - $wh['total_cogs_value'];
            $wh['total_stock_qty'] = round($wh['total_stock_qty'], 2);
            $wh['total_cogs_value'] = round($wh['total_cogs_value'], 2);
            $wh['total_selling_value'] = round($wh['total_selling_value'], 2);
            $wh['potential_profit'] = round($wh['potential_profit'], 2);

            return $wh;
        }, $warehouseMap));

        $byCategory = array_values(array_map(function ($cat) {
            $cat['potential_profit'] = $cat['total_selling_value'] - $cat['total_cogs_value'];
            $cat['total_stock_qty'] = round($cat['total_stock_qty'], 2);
            $cat['total_cogs_value'] = round($cat['total_cogs_value'], 2);
            $cat['total_selling_value'] = round($cat['total_selling_value'], 2);
            $cat['potential_profit'] = round($cat['potential_profit'], 2);

            return $cat;
        }, $categoryMap));

        return [
            'summary' => [
                'total_warehouses' => count($byWarehouse),
                'total_categories' => count($byCategory),
                'grand_total_qty' => round($grandQty, 2),
                'grand_total_cogs_value' => round($grandCogs, 2),
                'grand_total_selling_value' => round($grandSelling, 2),
                'grand_potential_profit' => round($grandSelling - $grandCogs, 2),
            ],
            'by_warehouse' => $byWarehouse,
            'by_category' => $byCategory,
        ];
    }
}
