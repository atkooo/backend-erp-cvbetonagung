<?php

namespace App\Http\Controllers\Api\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceiptNote;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;

class PurchasingQueryController extends Controller
{
    public function purchaseOrders(): JsonResponse
    {
        return response()->json([
            'data' => PurchaseOrder::query()
                ->with(['supplier', 'items.product'])
                ->orderByDesc('po_date')
                ->get(),
        ]);
    }

    public function receivings(): JsonResponse
    {
        return response()->json([
            'data' => GoodsReceiptNote::query()
                ->with(['purchaseOrder', 'warehouse', 'receiver', 'items.product'])
                ->orderByDesc('receipt_date')
                ->get(),
        ]);
    }
}
