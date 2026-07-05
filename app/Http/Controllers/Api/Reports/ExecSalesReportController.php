<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExecSalesReportController extends Controller
{
    /**
     * Laporan Omset Harian / Bulanan / Tahunan.
     * Menggabungkan invoice dengan payment untuk melihat omset & penerimaan.
     */
    public function dailySales(Request $request): JsonResponse
    {
        $period = $request->input('period', 'daily');
        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());

        $groupFormat = match ($period) {
            'monthly' => '%Y-%m',
            'yearly' => '%Y',
            default => '%Y-%m-%d',
        };

        $rows = DB::table('invoices')
            ->selectRaw("
                DATE_FORMAT(invoice_date, '{$groupFormat}') as period_label,
                COUNT(*) as invoice_count,
                COALESCE(SUM(total), 0) as gross_revenue,
                COALESCE(SUM(paid_amount), 0) as total_paid,
                COALESCE(SUM(total - paid_amount), 0) as outstanding
            ")
            ->whereBetween('invoice_date', [$dateFrom, $dateTo])
            ->groupByRaw("DATE_FORMAT(invoice_date, '{$groupFormat}')")
            ->orderBy('period_label')
            ->get();

        $summary = DB::table('invoices')
            ->selectRaw('
                COUNT(*) as total_invoices,
                COALESCE(SUM(total), 0) as total_revenue,
                COALESCE(SUM(paid_amount), 0) as total_paid,
                COALESCE(SUM(total - paid_amount), 0) as total_outstanding
            ')
            ->whereBetween('invoice_date', [$dateFrom, $dateTo])
            ->first();

        return response()->json([
            'data' => [
                'summary' => $summary,
                'rows' => $rows,
            ],
        ]);
    }

    /**
     * Laporan Laba Kotor.
     * Omset dari invoice vs HPP dari purchase orders per periode.
     */
    public function grossProfit(Request $request): JsonResponse
    {
        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());

        $revenue = (float) DB::table('invoices')
            ->whereBetween('invoice_date', [$dateFrom, $dateTo])
            ->sum('total');

        $cogs = (float) DB::table('purchase_orders')
            ->whereIn('status', ['received', 'completed', 'partial'])
            ->whereBetween('order_date', [$dateFrom, $dateTo])
            ->sum('total');

        $grossProfit = $revenue - $cogs;
        $margin = $revenue > 0 ? round(($grossProfit / $revenue) * 100, 2) : 0;

        $byCategory = DB::table('sales_order_items as soi')
            ->join('sales_orders as so', 'soi.sales_order_id', '=', 'so.id')
            ->join('products as p', 'soi.product_id', '=', 'p.id')
            ->leftJoin('product_categories as pc', 'p.category_id', '=', 'pc.id')
            ->selectRaw("
                COALESCE(pc.name, 'Tanpa Kategori') as category,
                COUNT(DISTINCT so.id) as order_count,
                COALESCE(SUM(soi.quantity), 0) as total_qty,
                COALESCE(SUM(soi.subtotal), 0) as total_revenue,
                COALESCE(SUM(soi.quantity * p.cost_price), 0) as total_cogs
            ")
            ->whereBetween('so.order_date', [$dateFrom, $dateTo])
            ->groupBy('pc.id', 'pc.name')
            ->orderByRaw('SUM(soi.subtotal) DESC')
            ->get()
            ->map(function ($row) {
                $row->gross_profit = $row->total_revenue - $row->total_cogs;
                $row->margin_pct = $row->total_revenue > 0
                    ? round(($row->gross_profit / $row->total_revenue) * 100, 2)
                    : 0;

                return $row;
            });

        return response()->json([
            'data' => [
                'summary' => [
                    'revenue' => $revenue,
                    'cogs' => $cogs,
                    'gross_profit' => $grossProfit,
                    'margin_pct' => $margin,
                ],
                'by_category' => $byCategory,
            ],
        ]);
    }

    /**
     * Laporan Piutang Jatuh Tempo (AR Aging).
     */
    public function arAging(Request $request): JsonResponse
    {
        $asOfDate = $request->input('as_of_date', now()->toDateString());

        $invoices = DB::table('invoices as i')
            ->join('customers as c', 'i.customer_id', '=', 'c.id')
            ->selectRaw('
                i.id,
                i.invoice_number,
                c.name as customer_name,
                i.invoice_date,
                i.due_date,
                i.total,
                i.paid_amount,
                (i.total - i.paid_amount) as outstanding,
                DATEDIFF(?, i.due_date) as days_overdue
            ', [$asOfDate])
            ->where('i.status', '!=', 'paid')
            ->havingRaw('outstanding > 0')
            ->orderByRaw('days_overdue DESC')
            ->get();

        $buckets = [
            'current' => 0.0,
            '1_30' => 0.0,
            '31_60' => 0.0,
            '61_90' => 0.0,
            'over_90' => 0.0,
        ];

        foreach ($invoices as $inv) {
            $days = (int) $inv->days_overdue;
            $amt = (float) $inv->outstanding;

            if ($days <= 0) {
                $buckets['current'] += $amt;
            } elseif ($days <= 30) {
                $buckets['1_30'] += $amt;
            } elseif ($days <= 60) {
                $buckets['31_60'] += $amt;
            } elseif ($days <= 90) {
                $buckets['61_90'] += $amt;
            } else {
                $buckets['over_90'] += $amt;
            }
        }

        return response()->json([
            'data' => [
                'as_of_date' => $asOfDate,
                'buckets' => $buckets,
                'invoices' => $invoices,
            ],
        ]);
    }

    /**
     * Analisis Produk Terlaris.
     */
    public function topProducts(Request $request): JsonResponse
    {
        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());
        $limit = (int) $request->input('limit', 20);

        $rows = DB::table('sales_order_items as soi')
            ->join('sales_orders as so', 'soi.sales_order_id', '=', 'so.id')
            ->join('products as p', 'soi.product_id', '=', 'p.id')
            ->leftJoin('product_categories as pc', 'p.category_id', '=', 'pc.id')
            ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
            ->selectRaw("
                p.sku,
                p.name as product_name,
                COALESCE(pc.name, '-') as category,
                COALESCE(u.name, '-') as unit,
                COUNT(DISTINCT so.id) as order_count,
                SUM(soi.quantity) as total_qty,
                SUM(soi.subtotal) as total_revenue
            ")
            ->whereBetween('so.order_date', [$dateFrom, $dateTo])
            ->groupBy('p.id', 'p.sku', 'p.name', 'pc.name', 'u.name')
            ->orderByRaw('SUM(soi.subtotal) DESC')
            ->limit($limit)
            ->get();

        $grandTotal = $rows->sum('total_revenue');

        $rows = $rows->values()->map(function ($row, $index) use ($grandTotal) {
            $row->rank = $index + 1;
            $row->contribution_pct = $grandTotal > 0
                ? round(($row->total_revenue / $grandTotal) * 100, 2)
                : 0;

            return $row;
        });

        return response()->json([
            'data' => [
                'grand_total' => $grandTotal,
                'rows' => $rows,
            ],
        ]);
    }
}
