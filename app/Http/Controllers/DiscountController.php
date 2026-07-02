<?php

namespace App\Http\Controllers;

use App\Models\Discount;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    public function index(Request $request)
    {
        $query = Discount::query();

        if ($request->has('q')) {
            $query->where('name', 'like', '%' . $request->q . '%');
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        return $query->latest()->paginate($request->get('per_page', 10));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:percentage,nominal',
            'value' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $discount = Discount::create($validated);
        return response()->json($discount, 201);
    }

    public function show(Discount $discount)
    {
        return $discount;
    }

    public function update(Request $request, Discount $discount)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:percentage,nominal',
            'value' => 'sometimes|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $discount->update($validated);
        return $discount;
    }

    public function destroy(Discount $discount)
    {
        $discount->delete();
        return response()->json(null, 204);
    }
}
