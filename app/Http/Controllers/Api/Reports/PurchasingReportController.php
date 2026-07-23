<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\PurchasingReportRequest;
use App\Services\Reports\GetApAgingReportAction;
use App\Services\Reports\GetPurchasePriceAnalysisReportAction;
use App\Services\Reports\GetSupplierPurchasesReportAction;
use Illuminate\Http\JsonResponse;

class PurchasingReportController extends Controller
{
    public function supplier(
        PurchasingReportRequest $request,
        GetSupplierPurchasesReportAction $action
    ): JsonResponse {
        $data = $action->execute($request->validated());

        return response()->json([
            'data' => $data,
        ]);
    }

    public function apAging(
        PurchasingReportRequest $request,
        GetApAgingReportAction $action
    ): JsonResponse {
        $data = $action->execute($request->validated());

        return response()->json([
            'data' => $data,
        ]);
    }

    public function priceAnalysis(
        PurchasingReportRequest $request,
        GetPurchasePriceAnalysisReportAction $action
    ): JsonResponse {
        $data = $action->execute($request->validated());

        return response()->json([
            'data' => $data,
        ]);
    }
}
