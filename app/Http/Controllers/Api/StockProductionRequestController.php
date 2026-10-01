<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StockProductionRequestStoreRequest;
use App\Models\StockProductionRequest;
use App\Services\CancellationService;
use App\Services\StockProductionRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockProductionRequestController extends Controller
{
    public function __construct(
        private readonly StockProductionRequestService $sprService,
        private readonly CancellationService $cancellationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = StockProductionRequest::query()
            ->with(['items.product', 'storageLocation', 'requestedBy', 'approvedBy', 'workOrders'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $requests = $query->paginate($request->integer('per_page', 20));

        return response()->json($requests);
    }

    public function store(StockProductionRequestStoreRequest $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $spr = $this->sprService->createRequest($request->validated(), $userId);

        return response()->json([
            'message' => 'Permintaan Produksi Stok berhasil dibuat.',
            'data' => $spr,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $spr = StockProductionRequest::query()
            ->with(['items.product', 'storageLocation', 'requestedBy', 'approvedBy', 'cancelledBy', 'workOrders.product', 'workOrders.tasks'])
            ->findOrFail($id);

        return response()->json(['data' => $spr]);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()?->id;
        $result = $this->sprService->approveRequest($id, $userId);

        return response()->json([
            'message' => 'Permintaan Produksi Stok berhasil disetujui dan Work Order diterbitkan.',
            'data' => $result['request'],
            'work_orders' => $result['work_orders'],
        ]);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $userId = $request->user()?->id ?? 'system';
        $spr = $this->cancellationService->cancelStockProductionRequest($id, $userId, $validated['reason']);

        return response()->json([
            'message' => 'Permintaan Produksi Stok berhasil dibatalkan.',
            'data' => $spr,
        ]);
    }
}
