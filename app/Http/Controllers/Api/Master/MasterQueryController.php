<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;

class MasterQueryController extends Controller
{
    public function customers(): JsonResponse
    {
        return response()->json(['data' => Customer::query()->orderBy('code')->get()]);
    }

    public function suppliers(): JsonResponse
    {
        return response()->json(['data' => Supplier::query()->orderBy('code')->get()]);
    }

    public function products(): JsonResponse
    {
        return response()->json([
            'data' => Product::query()
                ->with(['category', 'unit'])
                ->orderBy('sku')
                ->get(),
        ]);
    }
}
