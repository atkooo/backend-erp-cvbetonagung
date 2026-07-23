<?php

namespace App\Services\Reports;

use App\Models\SupplierPayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class GetApAgingReportAction
{
    /**
     * Generate Accounts Payable (AP) Aging Report.
     *
     * @param  array{
     *     as_of_date?: string|null,
     *     supplier_id?: string|null,
     *     search?: string|null
     * }  $filters
     * @return array{
     *     as_of_date: string,
     *     buckets: array{
     *         current: float,
     *         1_30: float,
     *         31_60: float,
     *         61_90: float,
     *         over_90: float
     *     },
     *     summary: array{
     *         total_open_payables: int,
     *         total_ap_amount: float,
     *         total_paid_amount: float,
     *         total_outstanding_ap: float
     *     },
     *     payables: array<int, array<string, mixed>>
     * }
     */
    public function execute(array $filters = []): array
    {
        $asOfDateStr = $filters['as_of_date'] ?? Carbon::now()->format('Y-m-d');
        $asOfDate = Carbon::parse($asOfDateStr)->endOfDay();

        $query = SupplierPayable::query()
            ->with(['supplier', 'purchaseOrder'])
            ->where('status', '!=', 'paid')
            ->where('created_at', '<=', $asOfDate);

        if (! empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('payable_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function (Builder $sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    })
                    ->orWhereHas('purchaseOrder', function (Builder $pq) use ($search) {
                        $pq->where('po_number', 'like', "%{$search}%");
                    });
            });
        }

        $payablesList = $query->orderBy('due_date')->get();

        $buckets = [
            'current' => 0.0,
            '1_30' => 0.0,
            '31_60' => 0.0,
            '61_90' => 0.0,
            'over_90' => 0.0,
        ];

        $totalApAmount = 0.0;
        $totalPaidAmount = 0.0;
        $totalOutstandingAp = 0.0;

        $rows = [];

        foreach ($payablesList as $p) {
            $amount = (float) $p->amount;
            $paid = (float) $p->paid_amount;
            $outstanding = max(0, $amount - $paid);

            if ($outstanding <= 0) {
                continue;
            }

            $dueDate = $p->due_date ? Carbon::parse($p->due_date) : $p->created_at;
            $daysOverdue = (int) $dueDate->diffInDays($asOfDate, false);

            if ($dueDate->isFuture()) {
                $daysOverdue = 0;
            } else {
                $daysOverdue = max(0, (int) $asOfDate->diffInDays($dueDate));
            }

            // Categorize into aging bucket
            if ($daysOverdue <= 0) {
                $buckets['current'] += $outstanding;
                $bucketKey = 'current';
                $bucketLabel = 'Belum Jatuh Tempo';
            } elseif ($daysOverdue <= 30) {
                $buckets['1_30'] += $outstanding;
                $bucketKey = '1_30';
                $bucketLabel = '1 - 30 Hari';
            } elseif ($daysOverdue <= 60) {
                $buckets['31_60'] += $outstanding;
                $bucketKey = '31_60';
                $bucketLabel = '31 - 60 Hari';
            } elseif ($daysOverdue <= 90) {
                $buckets['61_90'] += $outstanding;
                $bucketKey = '61_90';
                $bucketLabel = '61 - 90 Hari';
            } else {
                $buckets['over_90'] += $outstanding;
                $bucketKey = 'over_90';
                $bucketLabel = '> 90 Hari (Kritis)';
            }

            $totalApAmount += $amount;
            $totalPaidAmount += $paid;
            $totalOutstandingAp += $outstanding;

            $rows[] = [
                'id' => $p->id,
                'payable_number' => $p->payable_number ?: ($p->purchaseOrder?->po_number ?: '-'),
                'po_number' => $p->purchaseOrder?->po_number ?: '-',
                'supplier_id' => $p->supplier_id,
                'supplier_name' => $p->supplier?->name ?: '-',
                'supplier_code' => $p->supplier?->code ?: '-',
                'created_at' => $p->created_at ? $p->created_at->format('Y-m-d') : '-',
                'due_date' => $dueDate ? $dueDate->format('Y-m-d') : '-',
                'amount' => $amount,
                'paid_amount' => $paid,
                'outstanding' => round($outstanding, 2),
                'days_overdue' => $daysOverdue,
                'bucket_key' => $bucketKey,
                'bucket_label' => $bucketLabel,
                'status' => $p->status,
            ];
        }

        return [
            'as_of_date' => $asOfDateStr,
            'buckets' => [
                'current' => round($buckets['current'], 2),
                '1_30' => round($buckets['1_30'], 2),
                '31_60' => round($buckets['31_60'], 2),
                '61_90' => round($buckets['61_90'], 2),
                'over_90' => round($buckets['over_90'], 2),
            ],
            'summary' => [
                'total_open_payables' => count($rows),
                'total_ap_amount' => round($totalApAmount, 2),
                'total_paid_amount' => round($totalPaidAmount, 2),
                'total_outstanding_ap' => round($totalOutstandingAp, 2),
            ],
            'payables' => $rows,
        ];
    }
}
