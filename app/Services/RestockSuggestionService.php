<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RestockSuggestionService
{
    /**
     * Mengambil saran barang yang membutuhkan restok (stok kosong atau di bawah batas minimum).
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     summary: array{
     *         total_items: int,
     *         out_of_stock_count: int,
     *         low_stock_count: int,
     *         total_estimated_cost: float
     *     },
     *     rows: array<int, array<string, mixed>>
     * }
     */
    public function getSuggestions(array $filters = []): array
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

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $products = $query->orderBy('name')->get();

        $rows = [];
        $outOfStockCount = 0;
        $lowStockCount = 0;
        $totalEstimatedCost = 0.0;

        foreach ($products as $p) {
            $totalStock = (float) $p->stocks->sum('quantity');
            $minStock = (float) $p->min_stock;

            $stockStatus = 'safe';
            if ($totalStock <= 0) {
                $stockStatus = 'empty';
            } elseif ($totalStock <= $minStock) {
                $stockStatus = 'low';
            }

            // Filter hanya barang yang kosong atau menipis kecuali ada filter spesifik
            if (! empty($filters['stock_status'])) {
                if ($stockStatus !== $filters['stock_status']) {
                    continue;
                }
            } else {
                if ($stockStatus === 'safe') {
                    continue;
                }
            }

            if ($stockStatus === 'empty') {
                $outOfStockCount++;
            } elseif ($stockStatus === 'low') {
                $lowStockCount++;
            }

            $deficitQty = max(0, $minStock - $totalStock);
            // Default saran order: jika ada defisit pakai defisit, minimal 1
            $suggestedQty = $deficitQty > 0 ? $deficitQty : max(1.0, $minStock > 0 ? $minStock : 1.0);
            $costPrice = (float) $p->cost_price;
            $estimatedSubtotal = round($suggestedQty * $costPrice, 2);
            $totalEstimatedCost += $estimatedSubtotal;

            // Cari supplier terakhir yang memasok produk ini
            $lastPoItem = PurchaseOrderItem::query()
                ->where('product_id', $p->id)
                ->with('purchaseOrder.supplier')
                ->latest('created_at')
                ->first();

            $lastSupplier = $lastPoItem?->purchaseOrder?->supplier;

            $rows[] = [
                'product_id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'type' => $p->type ?? 'raw_material',
                'category_id' => $p->category_id,
                'category_name' => $p->category?->name ?: '-',
                'unit_id' => $p->unit_id,
                'unit_name' => $p->unit?->name ?: '-',
                'unit_code' => $p->unit?->code ?: '-',
                'current_stock' => $totalStock,
                'min_stock' => $minStock,
                'deficit_qty' => $deficitQty,
                'suggested_order_qty' => $suggestedQty,
                'cost_price' => $costPrice,
                'estimated_subtotal' => $estimatedSubtotal,
                'stock_status' => $stockStatus,
                'stock_status_label' => match ($stockStatus) {
                    'empty' => 'Stok Habis',
                    'low' => 'Stok Menipis',
                    default => 'Aman',
                },
                'last_supplier_id' => $lastSupplier?->id,
                'last_supplier_name' => $lastSupplier?->name,
            ];
        }

        return [
            'summary' => [
                'total_items' => count($rows),
                'out_of_stock_count' => $outOfStockCount,
                'low_stock_count' => $lowStockCount,
                'total_estimated_cost' => round($totalEstimatedCost, 2),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * Terbitkan dokumen Purchase Order (PO Pemasok) dari daftar barang restok yang dipilih.
     *
     * @param  array<string, mixed>  $data
     */
    public function generatePurchaseOrder(array $data, ?string $userId = null): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $userId): PurchaseOrder {
            $items = $data['items'] ?? [];
            abort_if(empty($items), 422, 'Minimal pilih satu barang untuk membuat PO restok.');

            $calculatedTotal = 0.0;
            foreach ($items as $item) {
                $qty = (float) ($item['quantity'] ?? 0);
                $price = (float) ($item['unit_price'] ?? 0);
                $calculatedTotal += ($qty * $price);
            }

            $po = PurchaseOrder::query()->create([
                'supplier_id' => $data['supplier_id'],
                'po_date' => $data['po_date'] ?? date('Y-m-d'),
                'total' => $calculatedTotal,
                'status' => 'draft',
                'notes' => $data['notes'] ?? 'PO Restok Otomatis Barang Kosong / Menipis',
                'created_by' => $userId,
            ]);

            foreach ($items as $item) {
                $qty = (float) $item['quantity'];
                $price = (float) $item['unit_price'];
                $subtotal = $qty * $price;

                PurchaseOrderItem::query()->create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'subtotal' => $subtotal,
                    'description' => $item['description'] ?? null,
                ]);
            }

            return $po->fresh(['supplier', 'items.product.unit']);
        });
    }
}
