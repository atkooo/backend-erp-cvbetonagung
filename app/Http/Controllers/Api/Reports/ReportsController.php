<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;

class ReportsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'total_invoiced' => (float) Invoice::query()->sum('total'),
                'total_paid' => (float) Payment::query()->where('status', 'verified')->sum('amount'),
                'stock_in_count' => StockMovement::query()->where('type', 'in')->count(),
                'stock_out_count' => StockMovement::query()->where('type', 'out')->count(),
            ],
        ]);
    }
}
