<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\InventoryReportRequest;
use App\Services\Reports\GetDeadStockReportAction;
use App\Services\Reports\GetInventoryValuationReportAction;
use App\Services\Reports\GetLowStockReportAction;
use App\Services\Reports\GetStockMutationReportAction;
use Illuminate\Http\JsonResponse;

class InventoryReportController extends Controller
{
    public function mutation(
        InventoryReportRequest $request,
        GetStockMutationReportAction $action
    ): JsonResponse {
        $data = $action->execute($request->validated());

        return response()->json([
            'data' => $data,
        ]);
    }

    public function lowStock(
        InventoryReportRequest $request,
        GetLowStockReportAction $action
    ): JsonResponse {
        $data = $action->execute($request->validated());

        return response()->json([
            'data' => $data,
        ]);
    }

    public function valuation(
        InventoryReportRequest $request,
        GetInventoryValuationReportAction $action
    ): JsonResponse {
        $data = $action->execute($request->validated());

        return response()->json([
            'data' => $data,
        ]);
    }

    public function deadStock(
        InventoryReportRequest $request,
        GetDeadStockReportAction $action
    ): JsonResponse {
        $data = $action->execute($request->validated());

        return response()->json([
            'data' => $data,
        ]);
    }
}
