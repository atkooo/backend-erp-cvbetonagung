<?php

namespace App\Services\Reports;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class GetProductMasterStockReportAction
{
    /**
     * Generate product master stock report dataset with COGS, selling price, margin, stock valuation, and QR codes.
     *
     * @param  array{
     *     category_id?: string|null,
     *     stock_status?: string|null,
     *     search?: string|null,
     *     type?: string|null
     * }  $filters
     * @return array{
     *     summary: array{
     *         total_products: int,
     *         total_stock_qty: float,
     *         total_cogs_value: float,
     *         total_selling_value: float,
     *         total_potential_profit: float,
     *         low_stock_count: int,
     *         out_of_stock_count: int
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

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('qr_value', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('sku')->get();

        $rows = [];
        $totalProducts = 0;
        $totalStockQty = 0.0;
        $totalCogsValue = 0.0;
        $totalSellingValue = 0.0;
        $lowStockCount = 0;
        $outOfStockCount = 0;

        foreach ($products as $product) {
            $totalStock = (float) $product->stocks->sum('quantity');
            $costPrice = (float) $product->cost_price;
            $sellingPrice = (float) $product->selling_price;
            $minStock = (float) $product->min_stock;

            $marginAmount = $sellingPrice - $costPrice;
            $marginPct = $sellingPrice > 0 ? round(($marginAmount / $sellingPrice) * 100, 2) : 0.0;

            $stockValueCogs = round($totalStock * $costPrice, 2);
            $stockValueSelling = round($totalStock * $sellingPrice, 2);
            $potentialProfit = round($stockValueSelling - $stockValueCogs, 2);

            if ($totalStock <= 0) {
                $statusStockKey = 'habis';
                $statusStockLabel = 'Habis';
                $outOfStockCount++;
            } elseif ($minStock > 0 && $totalStock <= $minStock) {
                $statusStockKey = 'menipis';
                $statusStockLabel = 'Menipis';
                $lowStockCount++;
            } else {
                $statusStockKey = 'aman';
                $statusStockLabel = 'Aman';
            }

            if (! empty($filters['stock_status'])) {
                $filterStatus = strtolower((string) $filters['stock_status']);
                if ($filterStatus !== $statusStockKey) {
                    continue;
                }
            }

            $totalProducts++;
            $totalStockQty += $totalStock;
            $totalCogsValue += $stockValueCogs;
            $totalSellingValue += $stockValueSelling;

            $rows[] = [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'type' => $product->type ?? 'barang_jadi',
                'category_id' => $product->category_id,
                'category_name' => $product->category?->name ?? '-',
                'unit_id' => $product->unit_id,
                'unit_name' => $product->unit?->name ?? '-',
                'unit_code' => $product->unit?->code ?? '-',
                'cost_price' => $costPrice,
                'selling_price' => $sellingPrice,
                'margin_amount' => $marginAmount,
                'margin_percentage' => $marginPct,
                'min_stock' => $minStock,
                'total_stock' => $totalStock,
                'stock_value_cogs' => $stockValueCogs,
                'stock_value_selling' => $stockValueSelling,
                'potential_profit' => $potentialProfit,
                'stock_status' => $statusStockKey,
                'stock_status_label' => $statusStockLabel,
                'qr_value' => $product->qr_value,
                'image_url' => $product->image_url,
                'status' => $product->status ?? 'Active',
            ];
        }

        return [
            'summary' => [
                'total_products' => $totalProducts,
                'total_stock_qty' => round($totalStockQty, 2),
                'total_cogs_value' => round($totalCogsValue, 2),
                'total_selling_value' => round($totalSellingValue, 2),
                'total_potential_profit' => round($totalSellingValue - $totalCogsValue, 2),
                'low_stock_count' => $lowStockCount,
                'out_of_stock_count' => $outOfStockCount,
            ],
            'rows' => $rows,
        ];
    }
}
