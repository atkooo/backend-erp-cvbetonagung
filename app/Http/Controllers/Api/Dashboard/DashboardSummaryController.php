<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\SupplierPayable;
use Illuminate\Http\JsonResponse;

class DashboardSummaryController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $openReceivables = Invoice::query()
            ->where('status', '!=', 'paid')
            ->selectRaw('COALESCE(SUM(total - paid_amount), 0) as total')
            ->value('total');

        $openPayables = SupplierPayable::query()
            ->where('status', '!=', 'paid')
            ->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as total')
            ->value('total');

        return response()->json([
            'data' => [
                'customers' => Customer::query()->count(),
                'products' => Product::query()->count(),
                'sales_orders' => SalesOrder::query()->count(),
                'purchase_orders' => PurchaseOrder::query()->count(),
                'open_receivables' => (float) $openReceivables,
                'open_payables' => (float) $openPayables,
            ],
        ]);
    }
}
