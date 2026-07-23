<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ProductMasterStockReportRequest;
use App\Services\Reports\GetProductMasterStockReportAction;
use Illuminate\Http\JsonResponse;

class ProductMasterStockReportController extends Controller
{
    public function __invoke(
        ProductMasterStockReportRequest $request,
        GetProductMasterStockReportAction $action
    ): JsonResponse {
        $data = $action->execute($request->validated());

        return response()->json([
            'data' => $data,
        ]);
    }
}
