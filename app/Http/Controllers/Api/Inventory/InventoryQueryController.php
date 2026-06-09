<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\ProductStock;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;

class InventoryQueryController extends Controller
{
    public function stocks(): JsonResponse
    {
        return response()->json([
            'data' => ProductStock::query()
                ->with(['product.unit', 'location'])
                ->get(),
        ]);
    }

    public function stockIns(): JsonResponse
    {
        return response()->json([
            'data' => StockMovement::query()
                ->with(['product', 'toLocation', 'handledBy'])
                ->where('type', 'in')
                ->orderByDesc('movement_at')
                ->get(),
        ]);
    }

    public function stockOuts(): JsonResponse
    {
        return response()->json([
            'data' => StockMovement::query()
                ->with(['product', 'fromLocation', 'handledBy'])
                ->where('type', 'out')
                ->orderByDesc('movement_at')
                ->get(),
        ]);
    }
}
