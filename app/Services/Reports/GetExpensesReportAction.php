<?php

namespace App\Services\Reports;

use App\Models\CashTransaction;

class GetExpensesReportAction
{
    /**
     * Generate Operational Expenses Report.
     *
     * @param  array{
     *     date_from?: string|null,
     *     date_to?: string|null,
     *     category?: string|null,
     *     search?: string|null
     * }  $filters
     * @return array{
     *     summary: array{
     *         total_expense_transactions: int,
     *         total_expenses_amount: float,
     *         top_expense_category: string
     *     },
     *     by_category: array<int, array<string, mixed>>,
     *     rows: array<int, array<string, mixed>>
     * }
     */
    public function execute(array $filters = []): array
    {
        $query = CashTransaction::query()
            ->with(['account', 'recordedBy'])
            ->where('type', 'out');

        if (! empty($filters['date_from'])) {
            $query->whereDate('transaction_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('transaction_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('transaction_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $expenses = $query->orderByDesc('transaction_date')->get();

        $categoryMap = [];
        $totalExpensesAmount = 0.0;
        $rows = [];

        foreach ($expenses as $e) {
            $amount = (float) $e->amount;
            $totalExpensesAmount += $amount;
            $catName = $e->category ?: 'Lain-lain';

            if (! isset($categoryMap[$catName])) {
                $categoryMap[$catName] = [
                    'category' => $catName,
                    'transaction_count' => 0,
                    'total_amount' => 0.0,
                ];
            }

            $categoryMap[$catName]['transaction_count']++;
            $categoryMap[$catName]['total_amount'] += $amount;

            $rows[] = [
                'id' => $e->id,
                'transaction_number' => $e->transaction_number ?: '-',
                'transaction_date' => $e->transaction_date ? $e->transaction_date->format('Y-m-d') : '-',
                'account_name' => $e->account?->name ?: 'Kas Utama',
                'category' => $catName,
                'description' => $e->description ?: '-',
                'amount' => $amount,
                'recorded_by' => $e->recordedBy?->name ?: '-',
            ];
        }

        $byCategory = array_values(array_map(function ($c) use ($totalExpensesAmount) {
            $c['total_amount'] = round($c['total_amount'], 2);
            $c['percentage'] = $totalExpensesAmount > 0 ? round(($c['total_amount'] / $totalExpensesAmount) * 100, 2) : 0.0;

            return $c;
        }, $categoryMap));

        usort($byCategory, fn ($a, $b) => $b['total_amount'] <=> $a['total_amount']);

        $topCategory = count($byCategory) > 0 ? $byCategory[0]['category'] : '-';

        return [
            'summary' => [
                'total_expense_transactions' => count($rows),
                'total_expenses_amount' => round($totalExpensesAmount, 2),
                'top_expense_category' => $topCategory,
            ],
            'by_category' => $byCategory,
            'rows' => $rows,
        ];
    }
}
