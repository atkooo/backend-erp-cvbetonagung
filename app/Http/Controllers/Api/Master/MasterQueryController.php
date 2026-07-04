<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class MasterQueryController extends Controller
{
    public function customers(): JsonResponse
    {
        return (new JsonResource(Customer::query()->orderBy('code')->get()))->response();
    }

    public function suppliers(): JsonResponse
    {
        return (new JsonResource(Supplier::query()->orderBy('code')->get()))->response();
    }

    public function products(): JsonResponse
    {
        return response()->json([
            'data' => Product::query()
                ->with(['category', 'unit', 'discount'])
                ->orderBy('sku')
                ->get(),
        ]);
    }
}
