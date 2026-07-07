<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreReturnRequest;
use App\Models\ProductReturn;
use App\Models\ReturnItem;
use App\Services\ReturnWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class ReturnController extends ApiResourceController
{
    private const RESOURCES = [
        'returns' => [
            'model' => ProductReturn::class,
            'searchable' => ['return_number', 'reason', 'qc_status'],
            'sortable' => ['return_number', 'type', 'qc_status', 'created_at'],
            'relations' => ['customer', 'supplier', 'salesOrder.invoices', 'purchaseOrder', 'createdBy', 'items.product'],
        ],
        'return-items' => [
            'model' => ReturnItem::class,
            'searchable' => ['notes'],
            'sortable' => ['quantity'],
            'relations' => ['productReturn', 'product'],
        ],
    ];

    protected ReturnWorkflowService $returnWorkflow;

    public function __construct(ReturnWorkflowService $returnWorkflow)
    {
        $this->returnWorkflow = $returnWorkflow;
    }

    protected function resources(): array
    {
        return self::RESOURCES;
    }

    public function index(Request $request, string $resource): JsonResponse
    {
        return $this->indexResource($request, $resource);
    }

    public function store(StoreReturnRequest $request, string $resource): JsonResponse
    {
        if ($resource === 'returns') {
            $validated = $request->validated();

            $return = DB::transaction(function () use ($validated) {
                $items = $validated['items'];
                unset($validated['items']);

                $validated['created_by'] = auth()->id();

                $return = ProductReturn::create($validated);

                foreach ($items as $item) {
                    $return->items()->create($item);
                }

                return $return;
            });

            return (new JsonResource($return->fresh($this->resources()['returns']['relations'] ?? [])))->response()->setStatusCode(201);
        }

        return $this->storeResource($resource, $request->validated());
    }

    public function show(string $resource, string $id): JsonResponse
    {
        return $this->showResource($resource, $id);
    }

    public function update(Request $request, string $resource, string $id): JsonResponse
    {
        if ($resource === 'returns' && $request->has('qc_status')) {
            $status = $request->input('qc_status');
            $return = ProductReturn::findOrFail($id);

            if ($status === 'approved' && $return->qc_status !== 'approved') {
                $allowBackorder = filter_var($request->input('allow_backorder', false), FILTER_VALIDATE_BOOLEAN);
                $updatedReturn = $this->returnWorkflow->approveReturn($id, $allowBackorder);

                return (new JsonResource($updatedReturn->fresh($this->resources()['returns']['relations'] ?? [])))->response();
            } elseif ($status === 'supplier_claim' && $return->qc_status !== 'supplier_claim') {
                $updatedReturn = $this->returnWorkflow->claimToSupplier($id);

                return (new JsonResource($updatedReturn->fresh($this->resources()['returns']['relations'] ?? [])))->response();
            }
        }

        // Use full validated data if there's a Request Form defined, otherwise $request->all()
        return $this->updateResource($resource, $id, $request->all());
    }

    public function destroy(string $resource, string $id): JsonResponse
    {
        return $this->destroyResource($resource, $id);
    }

    public function manualRefund(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'account_id' => 'required|uuid',
        ]);

        $this->returnWorkflow->manualRefundOverpayment($id, $request->input('account_id'));

        return response()->json(['message' => 'Refund processed successfully.']);
    }
}
