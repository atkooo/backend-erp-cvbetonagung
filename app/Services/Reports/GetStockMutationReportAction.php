<?php

namespace App\Services\Reports;

use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;

class GetStockMutationReportAction
{
    /**
     * Generate Stock Mutation Report dataset with filters, summary, and rows.
     *
     * @param  array{
     *     date_from?: string|null,
     *     date_to?: string|null,
     *     type?: string|null,
     *     product_id?: string|null,
     *     search?: string|null
     * }  $filters
     * @return array{
     *     summary: array{
     *         total_movements: int,
     *         total_qty_in: float,
     *         total_qty_out: float,
     *         total_qty_transfer: float,
     *         total_qty_adjustment: float
     *     },
     *     rows: array<int, array<string, mixed>>
     * }
     */
    public function execute(array $filters = []): array
    {
        $query = StockMovement::query()
            ->with(['product.unit', 'product.category', 'fromLocation.warehouse', 'toLocation.warehouse', 'handledBy']);

        if (! empty($filters['date_from'])) {
            $query->whereDate('movement_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('movement_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('product', function (Builder $pq) use ($search) {
                        $pq->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        $movements = $query->orderByDesc('movement_at')->get();

        $rows = [];
        $totalQtyIn = 0.0;
        $totalQtyOut = 0.0;
        $totalQtyTransfer = 0.0;
        $totalQtyAdjustment = 0.0;

        foreach ($movements as $m) {
            $qty = (float) $m->quantity;
            $type = (string) $m->type;

            match ($type) {
                'in' => $totalQtyIn += $qty,
                'out' => $totalQtyOut += $qty,
                'transfer' => $totalQtyTransfer += $qty,
                'adjustment' => $totalQtyAdjustment += $qty,
                default => null,
            };

            $fromName = $m->fromLocation ? ($m->fromLocation->warehouse?->name ? "{$m->fromLocation->warehouse->name} ({$m->fromLocation->code})" : $m->fromLocation->code) : '-';
            $toName = $m->toLocation ? ($m->toLocation->warehouse?->name ? "{$m->toLocation->warehouse->name} ({$m->toLocation->code})" : $m->toLocation->code) : '-';

            $rows[] = [
                'id' => $m->id,
                'movement_at' => $m->movement_at ? $m->movement_at->toIso8601String() : null,
                'movement_date' => $m->movement_at ? $m->movement_at->format('Y-m-d H:i:s') : '-',
                'type' => $type,
                'type_label' => match ($type) {
                    'in' => 'Stok Masuk',
                    'out' => 'Stok Keluar',
                    'transfer' => 'Transfer Lokasi',
                    'adjustment' => 'Penyesuaian Stok',
                    default => ucfirst($type),
                },
                'reference_number' => $m->reference_number ?: '-',
                'reference_type' => $m->reference_type ?: '-',
                'product_id' => $m->product_id,
                'sku' => $m->product?->sku ?: '-',
                'product_name' => $m->product?->name ?: '-',
                'category_name' => $m->product?->category?->name ?: '-',
                'unit_name' => $m->product?->unit?->name ?: '-',
                'unit_code' => $m->product?->unit?->code ?: '-',
                'quantity' => $qty,
                'from_location' => $fromName,
                'to_location' => $toName,
                'handled_by_name' => $m->handledBy?->name ?: '-',
                'notes' => $m->notes ?: '-',
            ];
        }

        return [
            'summary' => [
                'total_movements' => count($rows),
                'total_qty_in' => round($totalQtyIn, 2),
                'total_qty_out' => round($totalQtyOut, 2),
                'total_qty_transfer' => round($totalQtyTransfer, 2),
                'total_qty_adjustment' => round($totalQtyAdjustment, 2),
            ],
            'rows' => $rows,
        ];
    }
}
