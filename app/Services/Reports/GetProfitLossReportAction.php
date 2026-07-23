<?php

namespace App\Services\Reports;

use App\Models\CashTransaction;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;

class GetProfitLossReportAction
{
    /**
     * Generate Profit & Loss Statement (Laba Rugi).
     *
     * @param  array{
     *     date_from?: string|null,
     *     date_to?: string|null
     * }  $filters
     * @return array{
     *     period: array{
     *         date_from: string|null,
     *         date_to: string|null
     *     },
     *     summary: array{
     *         total_revenue: float,
     *         total_cogs: float,
     *         gross_profit: float,
     *         gross_margin_pct: float,
     *         total_operating_expenses: float,
     *         net_profit: float,
     *         net_margin_pct: float
     *     },
     *     breakdown: array{
     *         revenue_items: array<int, array<string, mixed>>,
     *         cogs_items: array<int, array<string, mixed>>,
     *         expense_categories: array<int, array<string, mixed>>
     *     }
     * }
     */
    public function execute(array $filters = []): array
    {
        $soQuery = SalesOrder::query()
            ->whereNotIn('status', ['cancelled']);

        if (! empty($filters['date_from'])) {
            $soQuery->whereDate('order_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $soQuery->whereDate('order_date', '<=', $filters['date_to']);
        }

        $totalRevenue = (float) $soQuery->sum('total');

        // COGS calculation from SalesOrderItem * Product cost_price
        $soIds = (clone $soQuery)->pluck('id');

        $items = SalesOrderItem::query()
            ->whereIn('sales_order_id', $soIds)
            ->with('product')
            ->get();

        $totalCogs = 0.0;
        foreach ($items as $item) {
            $costPrice = $item->product ? (float) $item->product->cost_price : (float) $item->unit_price * 0.7;
            $totalCogs += ((float) $item->quantity * $costPrice);
        }

        $grossProfit = $totalRevenue - $totalCogs;
        $grossMarginPct = $totalRevenue > 0 ? ($grossProfit / $totalRevenue) * 100 : 0.0;

        // Operating Expenses from CashTransaction
        $expQuery = CashTransaction::query()
            ->where('type', 'out');

        if (! empty($filters['date_from'])) {
            $expQuery->whereDate('transaction_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $expQuery->whereDate('transaction_date', '<=', $filters['date_to']);
        }

        $totalExpenses = (float) $expQuery->sum('amount');

        // Group expenses by category
        $expensesGrouped = CashTransaction::query()
            ->where('type', 'out')
            ->when(! empty($filters['date_from']), fn ($q) => $q->whereDate('transaction_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate('transaction_date', '<=', $filters['date_to']))
            ->selectRaw('category, SUM(amount) as total_amount, COUNT(id) as count')
            ->groupBy('category')
            ->get();

        $expenseCategories = [];
        foreach ($expensesGrouped as $eg) {
            $catName = $eg->category ?: 'Operasional';
            $amt = (float) $eg->total_amount;
            $expenseCategories[] = [
                'category' => $catName,
                'amount' => round($amt, 2),
                'count' => (int) $eg->count,
            ];
        }

        $netProfit = $grossProfit - $totalExpenses;
        $netMarginPct = $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0.0;

        return [
            'period' => [
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ],
            'summary' => [
                'total_revenue' => round($totalRevenue, 2),
                'total_cogs' => round($totalCogs, 2),
                'gross_profit' => round($grossProfit, 2),
                'gross_margin_pct' => round($grossMarginPct, 2),
                'total_operating_expenses' => round($totalExpenses, 2),
                'net_profit' => round($netProfit, 2),
                'net_margin_pct' => round($netMarginPct, 2),
            ],
            'breakdown' => [
                'revenue_items' => [
                    [
                        'description' => 'Pendapatan Penjualan Produksi & Beton',
                        'amount' => round($totalRevenue, 2),
                    ],
                ],
                'cogs_items' => [
                    [
                        'description' => 'Harga Pokok Penjualan (HPP / Raw Material)',
                        'amount' => round($totalCogs, 2),
                    ],
                ],
                'expense_categories' => $expenseCategories,
            ],
        ];
    }
}
