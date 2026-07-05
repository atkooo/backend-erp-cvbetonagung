<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CashTransaction;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SupplierPayable;
use Illuminate\Http\JsonResponse;

class FinanceQueryController extends Controller
{
    public function billing(): JsonResponse
    {
        return response()->json([
            'data' => Invoice::query()
                ->active()
                ->with(['customer', 'salesOrder', 'items.product'])
                ->orderByDesc('invoice_date')
                ->get(),
        ]);
    }

    public function cashier(): JsonResponse
    {
        return response()->json([
            'data' => Payment::query()
                ->active()
                ->with(['invoice.customer', 'verifiedBy'])
                ->orderByDesc('payment_date')
                ->get(),
        ]);
    }

    public function accountPayable(): JsonResponse
    {
        return response()->json([
            'data' => SupplierPayable::query()
                ->active()
                ->with(['supplier', 'purchaseOrder'])
                ->orderBy('due_date')
                ->get(),
        ]);
    }

    public function cashBank(): JsonResponse
    {
        return response()->json([
            'data' => [
                'accounts' => Account::query()->orderBy('code')->get(),
                'cash_transactions' => CashTransaction::query()
                    ->with(['account', 'recordedBy'])
                    ->orderByDesc('transaction_date')
                    ->get(),
            ],
        ]);
    }
}
