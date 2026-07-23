<?php

namespace App\Services\Reports;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;

class GetSupplierPurchasesReportAction
{
    /**
     * Generate Supplier Purchases Report grouped by Supplier.
     *
     * @param  array{
     *     date_from?: string|null,
     *     date_to?: string|null,
     *     supplier_id?: string|null,
     *     search?: string|null
     * }  $filters
     * @return array{
     *     summary: array{
     *         total_suppliers: int,
     *         total_po_count: int,
     *         total_purchase_amount: float,
     *         total_paid_amount: float,
     *         total_outstanding_ap: float
     *     },
     *     rows: array<int, array<string, mixed>>
     * }
     */
    public function execute(array $filters = []): array
    {
        $suppliersQuery = Supplier::query()->with(['purchaseOrders.supplierPayables']);

        if (! empty($filters['supplier_id'])) {
            $suppliersQuery->where('id', $filters['supplier_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $suppliersQuery->where(function (Builder $q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%");
            });
        }

        $suppliers = $suppliersQuery->orderBy('name')->get();

        $rows = [];
        $grandPoCount = 0;
        $grandPurchaseAmount = 0.0;
        $grandPaidAmount = 0.0;
        $grandOutstandingAp = 0.0;

        foreach ($suppliers as $supplier) {
            $poQuery = $supplier->purchaseOrders();

            if (! empty($filters['date_from'])) {
                $poQuery->whereDate('po_date', '>=', $filters['date_from']);
            }

            if (! empty($filters['date_to'])) {
                $poQuery->whereDate('po_date', '<=', $filters['date_to']);
            }

            $pos = $poQuery->with('supplierPayables')->get();

            $poCount = $pos->count();
            if ($poCount === 0 && (! empty($filters['date_from']) || ! empty($filters['date_to']))) {
                continue;
            }

            $totalPurchase = 0.0;
            $totalPaid = 0.0;
            $totalOutstanding = 0.0;

            foreach ($pos as $po) {
                $poTotal = (float) $po->total;
                $totalPurchase += $poTotal;

                $poPaid = (float) $po->supplierPayables->sum('paid_amount');
                $totalPaid += $poPaid;

                $poOutstanding = max(0, $poTotal - $poPaid);
                $totalOutstanding += $poOutstanding;
            }

            $grandPoCount += $poCount;
            $grandPurchaseAmount += $totalPurchase;
            $grandPaidAmount += $totalPaid;
            $grandOutstandingAp += $totalOutstanding;

            $rows[] = [
                'supplier_id' => $supplier->id,
                'supplier_code' => $supplier->code ?: '-',
                'supplier_name' => $supplier->name,
                'contact_name' => $supplier->contact_name ?: '-',
                'phone' => $supplier->phone ?: '-',
                'city' => $supplier->city ?: '-',
                'po_count' => $poCount,
                'total_purchase_amount' => round($totalPurchase, 2),
                'total_paid_amount' => round($totalPaid, 2),
                'total_outstanding_ap' => round($totalOutstanding, 2),
                'avg_transaction_value' => $poCount > 0 ? round($totalPurchase / $poCount, 2) : 0.0,
            ];
        }

        // Sort by total purchase amount descending
        usort($rows, fn ($a, $b) => $b['total_purchase_amount'] <=> $a['total_purchase_amount']);

        return [
            'summary' => [
                'total_suppliers' => count($rows),
                'total_po_count' => $grandPoCount,
                'total_purchase_amount' => round($grandPurchaseAmount, 2),
                'total_paid_amount' => round($grandPaidAmount, 2),
                'total_outstanding_ap' => round($grandOutstandingAp, 2),
            ],
            'rows' => $rows,
        ];
    }
}
