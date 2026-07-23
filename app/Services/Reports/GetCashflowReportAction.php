<?php

namespace App\Services\Reports;

use App\Models\CashTransaction;

class GetCashflowReportAction
{
    /**
     * Generate Cash Flow & General Ledger Report with running balance.
     *
     * @param  array{
     *     date_from?: string|null,
     *     date_to?: string|null,
     *     account_id?: string|null,
     *     type?: string|null,
     *     search?: string|null
     * }  $filters
     * @return array{
     *     summary: array{
     *         opening_balance: float,
     *         total_cash_in: float,
     *         total_cash_out: float,
     *         net_cash_flow: float,
     *         ending_balance: float
     *     },
     *     rows: array<int, array<string, mixed>>
     * }
     */
    public function execute(array $filters = []): array
    {
        $openingBalance = 0.0;

        // Calculate opening balance prior to date_from
        if (! empty($filters['date_from'])) {
            $prevQuery = CashTransaction::query()
                ->whereDate('transaction_date', '<', $filters['date_from']);

            if (! empty($filters['account_id'])) {
                $prevQuery->where('account_id', $filters['account_id']);
            }

            $openingIn = (float) (clone $prevQuery)->where('type', 'in')->sum('amount');
            $openingOut = (float) (clone $prevQuery)->where('type', 'out')->sum('amount');

            $openingBalance = $openingIn - $openingOut;
        }

        $query = CashTransaction::query()
            ->with(['account', 'recordedBy']);

        if (! empty($filters['date_from'])) {
            $query->whereDate('transaction_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('transaction_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('transaction_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $transactions = $query->orderBy('transaction_date')->orderBy('created_at')->get();

        $runningBalance = $openingBalance;
        $totalCashIn = 0.0;
        $totalCashOut = 0.0;
        $rows = [];

        foreach ($transactions as $t) {
            $amount = (float) $t->amount;
            $type = (string) $t->type;

            $debit = 0.0;
            $credit = 0.0;

            if ($type === 'in') {
                $debit = $amount;
                $totalCashIn += $amount;
                $runningBalance += $amount;
            } else {
                $credit = $amount;
                $totalCashOut += $amount;
                $runningBalance -= $amount;
            }

            $rows[] = [
                'id' => $t->id,
                'transaction_number' => $t->transaction_number ?: '-',
                'transaction_date' => $t->transaction_date ? $t->transaction_date->format('Y-m-d') : '-',
                'account_name' => $t->account?->name ?: 'Kas Utama',
                'type' => $type,
                'type_label' => $type === 'in' ? 'Kas Masuk (In)' : 'Kas Keluar (Out)',
                'category' => $t->category ?: 'Operasional',
                'description' => $t->description ?: '-',
                'debit' => $debit,
                'credit' => $credit,
                'running_balance' => round($runningBalance, 2),
                'recorded_by' => $t->recordedBy?->name ?: '-',
            ];
        }

        $netCashFlow = $totalCashIn - $totalCashOut;
        $endingBalance = $openingBalance + $netCashFlow;

        return [
            'summary' => [
                'opening_balance' => round($openingBalance, 2),
                'total_cash_in' => round($totalCashIn, 2),
                'total_cash_out' => round($totalCashOut, 2),
                'net_cash_flow' => round($netCashFlow, 2),
                'ending_balance' => round($endingBalance, 2),
            ],
            'rows' => $rows,
        ];
    }
}
