<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CashTransaction;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Models\SupplierPayable;
use Illuminate\Support\Facades\DB;

class FinanceWorkflowService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function verifyPayment(string $id, array $attributes): Payment
    {
        return DB::transaction(function () use ($id, $attributes): Payment {
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

            $remainingAmount = max(0, (float) $invoice->total - (float) $invoice->paid_amount);
            abort_if((float) $payment->amount > $remainingAmount, 422, 'Payment amount cannot exceed remaining AR balance.');

            $newPaidAmount = (float) $invoice->paid_amount + (float) $payment->amount;

            $invoice->forceFill([
                'paid_amount' => $newPaidAmount,
                'status' => $this->invoiceStatusFor($newPaidAmount, (float) $invoice->total),
            ])->save();

            $payment->forceFill([
                'status' => 'verified',
                'verified_by' => $attributes['verified_by'] ?? $payment->verified_by ?? auth()->id(),
                'verified_at' => $attributes['verified_at'] ?? now()->toDateTimeString(),
                'notes' => $attributes['notes'] ?? $payment->notes,
            ])->save();

            if ($payment->account_id) {
                $this->recordCashTransaction([
                    'account_id' => $payment->account_id,
                    'type' => 'in',
                    'amount' => $payment->amount,
                    'transaction_date' => $payment->payment_date,
                    'reference_type' => 'App\Models\Payment',
                    'reference_id' => $payment->id,
                    'description' => 'Penerimaan pembayaran faktur '.($invoice->invoice_number ?? $payment->invoice_id),
                ]);
            }

            return $payment;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function paySupplierPayable(string $id, array $attributes): SupplierPayable
    {
        return DB::transaction(function () use ($id, $attributes): SupplierPayable {
            $payable = SupplierPayable::query()
                ->with(['supplier', 'purchaseOrder'])
                ->lockForUpdate()
                ->whereKey($id)
                ->firstOrFail();

            abort_if($payable->status === 'cancelled', 422, 'Cancelled AP cannot be paid.');

            $remainingAmount = max(0, (float) $payable->amount - (float) $payable->paid_amount);
            abort_if($remainingAmount <= 0, 409, 'AP has already been fully paid.');
            abort_if((float) $attributes['amount'] > $remainingAmount, 422, 'Payment amount cannot exceed remaining AP balance.');

            $newPaidAmount = (float) $payable->paid_amount + (float) $attributes['amount'];

            $payable->forceFill([
                'paid_amount' => $newPaidAmount,
                'status' => SupplierPayable::resolveStatus($newPaidAmount, (float) $payable->amount),
            ])->save();

            if (! empty($attributes['account_id'])) {
                $this->recordCashTransaction([
                    'account_id' => $attributes['account_id'],
                    'type' => 'out',
                    'amount' => $attributes['amount'],
                    'transaction_date' => $attributes['paid_at'] ?? now()->toDateString(),
                    'reference_type' => 'App\Models\SupplierPayable',
                    'reference_id' => $payable->id,
                    'description' => $attributes['notes'] ?? 'Pembayaran hutang supplier '.$payable->payable_number,
                ]);
            }

            return $payable;
        });
    }

    /**
     * Buat Invoice baru beserta item-itemnya.
     * Jika ada sales_order_id dan tidak ada items eksplisit, copy dari SO items.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createInvoice(array $attributes): Invoice
    {
        return DB::transaction(function () use ($attributes): Invoice {
            $hasItems = array_key_exists('items', $attributes);
            $items = $attributes['items'] ?? [];
            unset($attributes['items']);

            $invoice = Invoice::query()->create($attributes);

            if (! empty($attributes['sales_order_id']) && (! $hasItems || empty($items))) {
                $salesOrder = SalesOrder::query()->with('items')->find($attributes['sales_order_id']);
                if ($salesOrder) {
                    $subtotal = 0;
                    foreach ($salesOrder->items as $soItem) {
                        $invoice->items()->create([
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
                    $globalDiscountAmount = (float) ($salesOrder->global_discount_amount ?? 0);
                    $invoice->forceFill([
                        'subtotal' => $subtotal,
                        'tax_amount' => $taxAmount,
                        'total' => max(0, $subtotal + $taxAmount - $globalDiscountAmount),
                    ])->save();

                    return $invoice;
                }
            }

            if ($hasItems && ! empty($items)) {
                $subtotal = 0;
                foreach ($items as $itemData) {
                    $itemSubtotal = (float) ($itemData['quantity'] ?? 0) * (float) ($itemData['unit_price'] ?? 0);
                    $subtotal += $itemSubtotal;
                    $invoice->items()->create([
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
                $invoice->forceFill([
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total' => $subtotal + $taxAmount,
                ])->save();
            }

            return $invoice;
        });
    }

    /**
     * Update Invoice dan sinkronisasi item-itemnya.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateInvoice(string $id, array $attributes): Invoice
    {
        return DB::transaction(function () use ($id, $attributes): Invoice {
            $invoice = Invoice::query()->whereKey($id)->firstOrFail();

            $hasItems = array_key_exists('items', $attributes);
            $items = $attributes['items'] ?? null;
            unset($attributes['items']);

            $invoice->fill($attributes)->save();

            if ($hasItems && $items !== null) {
                $invoice->items()->delete();

                $subtotal = 0;
                foreach ($items as $itemData) {
                    $itemSubtotal = (float) ($itemData['quantity'] ?? 0) * (float) ($itemData['unit_price'] ?? 0);
                    $subtotal += $itemSubtotal;
                    $invoice->items()->create([
                        'product_id' => $itemData['product_id'],
                        'description' => $itemData['description'] ?? null,
                        'piece_count' => $itemData['piece_count'] ?? null,
                        'length' => $itemData['length'] ?? null,
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'subtotal' => $itemSubtotal,
                    ]);
                }

                $taxAmount = $attributes['tax_amount'] ?? $invoice->tax_amount ?? 0;
                $invoice->forceFill([
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total' => $subtotal + $taxAmount,
                ])->save();
            }

            return $invoice;
        });
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function recordCashTransaction(array $attributes): CashTransaction
    {
        return DB::transaction(function () use ($attributes): CashTransaction {
            $account = Account::query()->lockForUpdate()->findOrFail($attributes['account_id']);

            if (empty($attributes['transaction_number'])) {
                $prefix = $attributes['type'] === 'in' ? 'CASH-IN-' : 'CASH-OUT-';
                $attributes['transaction_number'] = $prefix.date('Ym').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            }

            if (empty($attributes['category'])) {
                $attributes['category'] = $attributes['type'] === 'in' ? 'revenue' : 'expense';
            }

            $transaction = CashTransaction::create($attributes);

            if ($transaction->type === 'in') {
                $account->balance += $transaction->amount;
            } else {
                $account->balance -= $transaction->amount;
            }
            $account->save();

            return $transaction;
        });
    }
}
