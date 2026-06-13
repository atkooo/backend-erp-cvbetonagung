<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\FinanceRequest;
use App\Http\Requests\Api\PaySupplierPayableRequest;
use App\Http\Requests\Api\VerifyPaymentRequest;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\ProjectTermin;
use App\Models\Account;
use App\Models\CashTransaction;
use App\Services\FinanceWorkflowService;
use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

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
        if ($resource === 'invoices') {
            return $this->storeWithItems($resource, $request->validated());
        }

        if ($resource === 'cash-transactions') {
            $transaction = $this->financeWorkflow->recordCashTransaction($request->validated());
            $transaction->load(['account', 'recordedBy']);
            return response()->json(['data' => $transaction], 201);
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
            return $this->updateWithItems($resource, $id, $request->validated());
        }

        return $this->updateResource($resource, $id, $request->validated());
    }

    protected function storeWithItems(string $resource, array $attributes): JsonResponse
    {
        $config = $this->resourceConfig($resource);
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
        $modelClass = $config['model'];

        $hasItems = array_key_exists('items', $attributes);
        $items = $attributes['items'] ?? [];
        unset($attributes['items']);

        $model = DB::transaction(function () use ($modelClass, $attributes, $items, $resource, $hasItems) {
            /** @var \Illuminate\Database\Eloquent\Model $model */
            $model = $modelClass::query()->create($attributes);

            if ($resource === 'invoices' && !empty($attributes['sales_order_id'])) {
                $salesOrder = SalesOrder::query()->with('items')->find($attributes['sales_order_id']);
                if ($salesOrder) {
                    if (!$hasItems || empty($items)) {
                        $subtotal = 0;
                        foreach ($salesOrder->items as $soItem) {
                            $model->items()->create([
                                'product_id' => $soItem->product_id,
                                'description' => $soItem->description,
                                'piece_count' => $soItem->piece_count,
                                'length' => $soItem->length,
                                'quantity' => $soItem->quantity,
                                'unit_price' => $soItem->unit_price,
                                'subtotal' => $soItem->subtotal,
                            ]);
                            $subtotal += $soItem->subtotal;
                        }
                        $taxAmount = $attributes['tax_amount'] ?? 0;
                        $model->forceFill([
                            'subtotal' => $subtotal,
                            'tax_amount' => $taxAmount,
                            'total' => $subtotal + $taxAmount,
                        ])->save();

                        return $model;
                    }
                }
            }

            if ($hasItems) {
                $subtotal = 0;
                foreach ($items as $itemData) {
                    $itemSubtotal = ($itemData['quantity'] ?? 0) * ($itemData['unit_price'] ?? 0);
                    $subtotal += $itemSubtotal;

                    $model->items()->create([
                        'product_id' => $itemData['product_id'],
                        'description' => $itemData['description'] ?? null,
                        'piece_count' => $itemData['piece_count'] ?? null,
                        'length' => $itemData['length'] ?? null,
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'subtotal' => $itemSubtotal,
                    ]);
                }

                $taxAmount = $attributes['tax_amount'] ?? 0;
                $model->forceFill([
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total' => $subtotal + $taxAmount,
                ])->save();
            }

            return $model;
        });

        return response()->json(['data' => $model->fresh($config['relations'] ?? [])], 201);
    }

    protected function updateWithItems(string $resource, string $id, array $attributes): JsonResponse
    {
        $config = $this->resourceConfig($resource);
        $model = $this->findResourceModel($config, $id);

        $hasItems = array_key_exists('items', $attributes);
        $items = $attributes['items'] ?? null;
        unset($attributes['items']);

        DB::transaction(function () use ($model, $attributes, $items, $resource, $hasItems) {
            $model->fill($attributes);
            $model->save();

            if ($hasItems && $items !== null) {
                $model->items()->delete();

                $subtotal = 0;
                foreach ($items as $itemData) {
                    $itemSubtotal = ($itemData['quantity'] ?? 0) * ($itemData['unit_price'] ?? 0);
                    $subtotal += $itemSubtotal;

                    $model->items()->create([
                        'product_id' => $itemData['product_id'],
                        'description' => $itemData['description'] ?? null,
                        'piece_count' => $itemData['piece_count'] ?? null,
                        'length' => $itemData['length'] ?? null,
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'subtotal' => $itemSubtotal,
                    ]);
                }

                $taxAmount = $attributes['tax_amount'] ?? $model->tax_amount ?? 0;
                $model->forceFill([
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total' => $subtotal + $taxAmount,
                ])->save();
            }
        });

        return response()->json(['data' => $model->fresh($config['relations'] ?? [])]);
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

    public function paySupplierPayable(PaySupplierPayableRequest $request, string $id): JsonResponse
    {
        $payable = $this->financeWorkflow->paySupplierPayable($id, $request->validated());

        return response()->json([
            'data' => $payable->fresh(['supplier', 'purchaseOrder']),
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
