<?php

namespace App\Services\Reports;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class GetDeadStockReportAction
{
    /**
     * Generate Dead Stock Analysis Report for idle inventory with no recent movements or sales.
     *
     * @param  array{
     *     days?: int|string|null,
     *     category_id?: string|null,
     *     search?: string|null
     * }  $filters
     * @return array{
     *     summary: array{
     *         total_dead_stock_items: int,
     *         total_idle_qty: float,
     *         total_tied_cogs_value: float,
     *         total_tied_selling_value: float,
     *         threshold_days: int
     *     },
     *     rows: array<int, array<string, mixed>>
     * }
     */
    public function execute(array $filters = []): array
    {
        $thresholdDays = (int) ($filters['days'] ?? 30);
        if ($thresholdDays < 1) {
            $thresholdDays = 30;
        }

        $cutoffDate = Carbon::now()->subDays($thresholdDays);

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
        $totalIdleQty = 0.0;
        $totalTiedCogs = 0.0;
        $totalTiedSelling = 0.0;

        foreach ($products as $p) {
            $totalStock = (float) $p->stocks->sum('quantity');
            if ($totalStock <= 0) {
                continue;
            }

            // Find last stock movement or creation date
            $lastMovement = StockMovement::query()
                ->where('product_id', $p->id)
                ->whereIn('type', ['out', 'transfer'])
                ->orderByDesc('movement_at')
                ->first();

            $lastActiveDate = $lastMovement ? $lastMovement->movement_at : $p->created_at;
            $daysIdle = $lastActiveDate ? (int) Carbon::now()->diffInDays($lastActiveDate) : 999;

            if ($daysIdle < $thresholdDays) {
                continue;
            }

            $costPrice = (float) $p->cost_price;
            $sellingPrice = (float) $p->selling_price;

            $tiedCogs = $totalStock * $costPrice;
            $tiedSelling = $totalStock * $sellingPrice;

            $totalIdleQty += $totalStock;
            $totalTiedCogs += $tiedCogs;
            $totalTiedSelling += $tiedSelling;

            $rows[] = [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'type' => $p->type,
                'category_name' => $p->category?->name ?: '-',
                'unit_name' => $p->unit?->name ?: '-',
                'unit_code' => $p->unit?->code ?: '-',
                'total_stock' => $totalStock,
                'cost_price' => $costPrice,
                'selling_price' => $sellingPrice,
                'tied_cogs_value' => round($tiedCogs, 2),
                'tied_selling_value' => round($tiedSelling, 2),
                'last_active_date' => $lastActiveDate ? $lastActiveDate->format('Y-m-d') : '-',
                'days_idle' => $daysIdle,
                'risk_level' => match (true) {
                    $daysIdle >= 90 => 'Kritis (90+ hari)',
                    $daysIdle >= 60 => 'Tinggi (60-89 hari)',
                    default => 'Sedang (30-59 hari)',
                },
            ];
        }

        // Sort by longest idle time
        usort($rows, fn ($a, $b) => $b['days_idle'] <=> $a['days_idle']);

        return [
            'summary' => [
                'total_dead_stock_items' => count($rows),
                'total_idle_qty' => round($totalIdleQty, 2),
                'total_tied_cogs_value' => round($totalTiedCogs, 2),
                'total_tied_selling_value' => round($totalTiedSelling, 2),
                'threshold_days' => $thresholdDays,
            ],
            'rows' => $rows,
        ];
    }
}
