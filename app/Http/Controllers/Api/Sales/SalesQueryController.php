<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\SalesOrder;
use Illuminate\Http\JsonResponse;

class SalesQueryController extends Controller
{
    public function quotations(): JsonResponse
    {
        return response()->json([
            'data' => Quotation::query()
                ->with(['customer', 'items.product'])
                ->orderByDesc('quotation_date')
                ->get(),
        ]);
    }

    public function orders(): JsonResponse
    {
        return response()->json([
            'data' => SalesOrder::query()
                ->with(['customer', 'quotation', 'items.product'])
                ->orderByDesc('order_date')
                ->get(),
        ]);
    }

    public function invoices(): JsonResponse
    {
        return response()->json([
            'data' => Invoice::query()
                ->with(['customer', 'salesOrder', 'items.product'])
                ->orderByDesc('invoice_date')
                ->get(),
        ]);
    }
}
