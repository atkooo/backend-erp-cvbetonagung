<?php

namespace App\Services\Reports;

use App\Models\Product;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Builder;

class GetPurchasePriceAnalysisReportAction
{
    /**
     * Generate Purchase Price Analysis Report (RFQ / Cost History).
     *
     * @param  array{
     *     date_from?: string|null,
     *     date_to?: string|null,
     *     product_id?: string|null,
     *     search?: string|null
     * }  $filters
     * @return array{
     *     summary: array{
     *         total_analyzed_products: int,
     *         total_po_items: int
     *     },
     *     rows: array<int, array<string, mixed>>
     * }
     */
    public function execute(array $filters = []): array
    {
        $productsQuery = Product::query()->with(['category', 'unit']);

        if (! empty($filters['product_id'])) {
            $productsQuery->where('id', $filters['product_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $productsQuery->where(function (Builder $q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $products = $productsQuery->orderBy('sku')->get();

        $rows = [];
        $totalPoItemsAnalyzed = 0;

        foreach ($products as $p) {
            $itemQuery = PurchaseOrderItem::query()
                ->where('product_id', $p->id)
                ->whereHas('purchaseOrder', function (Builder $poq) use ($filters) {
                    if (! empty($filters['date_from'])) {
                        $poq->whereDate('po_date', '>=', $filters['date_from']);
                    }
                    if (! empty($filters['date_to'])) {
                        $poq->whereDate('po_date', '<=', $filters['date_to']);
                    }
                })
                ->with(['purchaseOrder.supplier']);

            $items = $itemQuery->get();
            $itemCount = $items->count();

            if ($itemCount === 0 && (! empty($filters['date_from']) || ! empty($filters['date_to']) || ! empty($filters['product_id']))) {
                continue;
            }

            $totalPoItemsAnalyzed += $itemCount;

            $prices = $items->pluck('unit_price')->map(fn ($price) => (float) $price)->filter(fn ($p) => $p > 0);

            $masterCostPrice = (float) $p->cost_price;
            $latestPrice = $prices->isNotEmpty() ? $prices->last() : $masterCostPrice;
            $minPrice = $prices->isNotEmpty() ? $prices->min() : $masterCostPrice;
            $maxPrice = $prices->isNotEmpty() ? $prices->max() : $masterCostPrice;
            $avgPrice = $prices->isNotEmpty() ? $prices->avg() : $masterCostPrice;

            // Price variation vs master cost price
            $priceVariancePct = $masterCostPrice > 0 ? (($latestPrice - $masterCostPrice) / $masterCostPrice) * 100 : 0.0;

            // Latest supplier name
            $lastItem = $items->last();
            $latestSupplierName = $lastItem && $lastItem->purchaseOrder && $lastItem->purchaseOrder->supplier
                ? $lastItem->purchaseOrder->supplier->name
                : '-';

            $rows[] = [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'category_name' => $p->category?->name ?: '-',
                'unit_name' => $p->unit?->name ?: '-',
                'unit_code' => $p->unit?->code ?: '-',
                'master_cost_price' => $masterCostPrice,
                'po_count' => $itemCount,
                'latest_purchase_price' => round($latestPrice, 2),
                'min_purchase_price' => round($minPrice, 2),
                'max_purchase_price' => round($maxPrice, 2),
                'avg_purchase_price' => round($avgPrice, 2),
                'latest_supplier_name' => $latestSupplierName,
                'price_variance_pct' => round($priceVariancePct, 2),
                'price_trend' => match (true) {
                    $priceVariancePct > 0 => 'Naik',
                    $priceVariancePct < 0 => 'Turun',
                    default => 'Stabil',
                },
            ];
        }

        return [
            'summary' => [
                'total_analyzed_products' => count($rows),
                'total_po_items' => $totalPoItemsAnalyzed,
            ],
            'rows' => $rows,
        ];
    }
}
