<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\FinanceReportRequest;
use App\Services\Reports\GetCashflowReportAction;
use App\Services\Reports\GetExpensesReportAction;
use App\Services\Reports\GetProfitLossReportAction;
use Illuminate\Http\JsonResponse;

class FinanceReportController extends Controller
{
    public function cashflow(
        FinanceReportRequest $request,
        GetCashflowReportAction $action
    ): JsonResponse {
        $data = $action->execute($request->validated());

        return response()->json([
            'data' => $data,
        ]);
    }

    public function expenses(
        FinanceReportRequest $request,
        GetExpensesReportAction $action
    ): JsonResponse {
        $data = $action->execute($request->validated());

        return response()->json([
            'data' => $data,
        ]);
    }

    public function profitLoss(
        FinanceReportRequest $request,
        GetProfitLossReportAction $action
    ): JsonResponse {
        $data = $action->execute($request->validated());

        return response()->json([
            'data' => $data,
        ]);
    }
}
