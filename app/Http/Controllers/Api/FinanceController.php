<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\FinanceRequest;
use App\Http\Requests\Api\VerifyPaymentRequest;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\ProjectTermin;
use App\Services\FinanceWorkflowService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FinanceController extends ApiResourceController
{
    /**
     * @var array<string, array{model: class-string<Model>, searchable: array<int, string>, sortable: array<int, string>, relations?: array<int, string>}>
     */
    private const RESOURCES = [
        'invoices' => [
            'model' => Invoice::class,
            'searchable' => ['invoice_number'],
            'sortable' => ['invoice_number', 'invoice_date', 'due_date', 'status', 'total', 'paid_amount', 'created_at'],
            'relations' => ['salesOrder', 'project', 'customer', 'items.product', 'payments', 'projectTermins'],
        ],
        'invoice-items' => [
            'model' => InvoiceItem::class,
            'searchable' => ['description'],
            'sortable' => ['quantity', 'unit_price', 'subtotal'],
            'relations' => ['invoice', 'product'],
        ],
        'payments' => [
            'model' => Payment::class,
            'searchable' => ['payment_number', 'notes'],
            'sortable' => ['payment_number', 'payment_date', 'method', 'amount', 'status', 'created_at'],
            'relations' => ['invoice', 'verifiedBy'],
        ],
        'project-termins' => [
            'model' => ProjectTermin::class,
            'searchable' => ['phase'],
            'sortable' => ['phase', 'amount', 'due_date', 'status', 'paid_at'],
            'relations' => ['project', 'invoice'],
        ],
    ];

    public function __construct(private readonly FinanceWorkflowService $financeWorkflow)
    {
    }

    /**
     * @return array<string, array{model: class-string<Model>, searchable: array<int, string>, sortable: array<int, string>, relations?: array<int, string>}>
     */
    protected function resources(): array
    {
        return self::RESOURCES;
    }

    public function index(Request $request, string $resource): JsonResponse
    {
        return $this->indexResource($request, $resource);
    }

    public function store(FinanceRequest $request, string $resource): JsonResponse
    {
        return $this->storeResource($resource, $request->validated());
    }

    public function show(string $resource, string $id): JsonResponse
    {
        return $this->showResource($resource, $id);
    }

    public function update(FinanceRequest $request, string $resource, string $id): JsonResponse
    {
        return $this->updateResource($resource, $id, $request->validated());
    }

    public function destroy(string $resource, string $id): JsonResponse|Response
    {
        return $this->destroyResource($resource, $id);
    }

    public function verifyPayment(VerifyPaymentRequest $request, string $id): JsonResponse
    {
        $payment = $this->financeWorkflow->verifyPayment($id, $request->validated());

        return response()->json([
            'data' => $payment->fresh(['invoice', 'verifiedBy']),
        ]);
    }

    protected function filterableColumns(): array
    {
        return [
            'customer_id',
            'sales_order_id',
            'project_id',
            'invoice_id',
            'product_id',
            'status',
            'method',
        ];
    }
}
