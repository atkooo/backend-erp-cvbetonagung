<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\CancelDocumentRequest;
use App\Http\Requests\Api\FinanceRequest;
use App\Http\Requests\Api\PaySupplierPayableRequest;
use App\Http\Requests\Api\VerifyPaymentRequest;
use App\Models\Account;
use App\Models\CashTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\ProjectTermin;
use App\Services\CancellationService;
use App\Services\FinanceWorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
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
        'accounts' => [
            'model' => Account::class,
            'searchable' => ['code', 'name', 'description'],
            'sortable' => ['code', 'name', 'type', 'balance', 'currency'],
            'relations' => [],
        ],
        'cash-transactions' => [
            'model' => CashTransaction::class,
            'searchable' => ['transaction_number', 'description', 'reference_type'],
            'sortable' => ['transaction_date', 'transaction_number', 'amount', 'type', 'category'],
            'relations' => ['account', 'recordedBy'],
        ],
    ];

    public function __construct(private readonly FinanceWorkflowService $financeWorkflow) {}

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
        if ($resource === 'invoices') {
            $invoice = $this->financeWorkflow->createInvoice($request->validated());
            $config = $this->resourceConfig($resource);

            return (new JsonResource($invoice->fresh($config['relations'] ?? [])))->response()->setStatusCode(201);
        }

        if ($resource === 'cash-transactions') {
            $transaction = $this->financeWorkflow->recordCashTransaction($request->validated());
            $transaction->load(['account', 'recordedBy']);

            return (new JsonResource($transaction))->response()->setStatusCode(201);
        }

        if ($resource === 'payments') {
            $invoiceId = $request->input('invoice_id');
            if ($invoiceId) {
                // Check if there is already a pending payment for this invoice
                $pendingPayment = Payment::where('invoice_id', $invoiceId)
                    ->where('status', 'pending')
                    ->first();

                if ($pendingPayment) {
                    return response()->json([
                        'message' => 'Tidak dapat membuat pembayaran baru karena masih ada pembayaran berstatus Pending untuk Invoice ini.',
                    ], 422);
                }
            }
        }

        return $this->storeResource($resource, $request->validated());
    }

    public function show(string $resource, string $id): JsonResponse
    {
        return $this->showResource($resource, $id);
    }

    public function update(FinanceRequest $request, string $resource, string $id): JsonResponse
    {
        if ($resource === 'invoices') {
            $invoice = $this->financeWorkflow->updateInvoice($id, $request->validated());
            $config = $this->resourceConfig($resource);

            return (new JsonResource($invoice->fresh($config['relations'] ?? [])))->response();
        }

        return $this->updateResource($resource, $id, $request->validated());
    }

    public function destroy(string $resource, string $id): JsonResponse|Response
    {
        if ($resource === 'accounts') {
            $account = Account::findOrFail($id);

            if ($account->balance != 0) {
                return response()->json([
                    'message' => 'Akun tidak dapat dihapus karena masih memiliki saldo.',
                ], 422);
            }

            if ($account->transactions()->exists()) {
                return response()->json([
                    'message' => 'Akun tidak dapat dihapus karena sudah memiliki riwayat transaksi.',
                ], 422);
            }
        }

        return $this->destroyResource($resource, $id);
    }

    public function verifyPayment(VerifyPaymentRequest $request, string $id): JsonResponse
    {
        $payment = $this->financeWorkflow->verifyPayment($id, $request->validated());

        return response()->json([
            'data' => $payment->fresh(['invoice', 'verifiedBy']),
        ]);
    }

    public function paySupplierPayable(PaySupplierPayableRequest $request, string $id): JsonResponse
    {
        $payable = $this->financeWorkflow->paySupplierPayable($id, $request->validated());

        return response()->json([
            'data' => $payable->fresh(['supplier', 'purchaseOrder']),
        ]);
    }

    public function cancelInvoice(CancelDocumentRequest $request, string $id, CancellationService $service): JsonResponse
    {
        $invoice = $service->cancelInvoice($id, auth()->id(), $request->input('reason', ''));
        $config = $this->resourceConfig('invoices');

        return (new JsonResource($invoice->fresh($config['relations'] ?? [])))->response();
    }

    public function cancelPayment(CancelDocumentRequest $request, string $id, CancellationService $service): JsonResponse
    {
        $payment = $service->cancelPayment($id, auth()->id(), $request->input('reason', ''));
        $config = $this->resourceConfig('payments');

        return (new JsonResource($payment->fresh($config['relations'] ?? [])))->response();
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

    protected function resourceQuery(array $config): Builder
    {
        $query = parent::resourceQuery($config);

        if ($config['model'] === Account::class) {
            $query->addSelect('*')
                ->selectSub(function ($query) {
                    $query->selectRaw("COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE -amount END), 0)")
                        ->from('cash_transactions')
                        ->whereColumn('cash_transactions.account_id', 'accounts.id');
                }, 'balance')
                ->withCasts(['balance' => 'decimal:2']);
        }

        return $query;
    }
}
