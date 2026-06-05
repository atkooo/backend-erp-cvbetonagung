<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\FinanceRequest;
use App\Http\Requests\Api\VerifyPaymentRequest;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\ProjectTermin;
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
    ];

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
        $validated = $request->validated();

        $payment = DB::transaction(function () use ($id, $validated): Payment {
            $payment = Payment::query()
                ->with('invoice')
                ->lockForUpdate()
                ->whereKey($id)
                ->firstOrFail();

            abort_if($payment->status === 'verified', 409, 'Payment has already been verified.');
            abort_if($payment->status === 'failed', 422, 'Failed payment cannot be verified.');

            $invoice = Invoice::query()
                ->lockForUpdate()
                ->whereKey($payment->invoice_id)
                ->firstOrFail();

            $newPaidAmount = (float) $invoice->paid_amount + (float) $payment->amount;

            $invoice->forceFill([
                'paid_amount' => $newPaidAmount,
                'status' => $this->invoiceStatusFor($newPaidAmount, (float) $invoice->total),
            ])->save();

            $payment->forceFill([
                'status' => 'verified',
                'verified_by' => $validated['verified_by'] ?? $payment->verified_by,
                'verified_at' => $validated['verified_at'],
                'notes' => $validated['notes'] ?? $payment->notes,
            ])->save();

            return $payment;
        });

        return response()->json([
            'data' => $payment->fresh(['invoice', 'verifiedBy']),
        ]);
    }

    private function invoiceStatusFor(float $paidAmount, float $total): string
    {
        if ($paidAmount <= 0) {
            return 'unpaid';
        }

        if ($paidAmount < $total) {
            return 'partial';
        }

        return 'paid';
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
