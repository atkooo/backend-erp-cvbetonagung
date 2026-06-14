<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\CashTransaction;
use App\Models\Account;
use App\Models\SupplierPayable;
use Illuminate\Support\Facades\DB;

class FinanceWorkflowService
{
    /**
     * @param array<string, mixed> $attributes
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
                    'description' => 'Penerimaan pembayaran faktur ' . ($invoice->invoice_number ?? $payment->invoice_id),
                ]);
            }

            return $payment;
        });
    }

    /**
     * @param array<string, mixed> $attributes
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
                'status' => $this->payableStatusFor($newPaidAmount, (float) $payable->amount),
            ])->save();

            if (!empty($attributes['account_id'])) {
                $this->recordCashTransaction([
                    'account_id' => $attributes['account_id'],
                    'type' => 'out',
                    'amount' => $attributes['amount'],
                    'transaction_date' => $attributes['paid_at'] ?? now()->toDateString(),
                    'reference_type' => 'App\Models\SupplierPayable',
                    'reference_id' => $payable->id,
                    'description' => $attributes['notes'] ?? 'Pembayaran hutang supplier ' . $payable->payable_number,
                ]);
            }

            return $payable;
        });
    }

    private function payableStatusFor(float $paidAmount, float $amount): string
    {
        if ($paidAmount <= 0) {
            return 'open';
        }

        if ($paidAmount < $amount) {
            return 'partial';
        }

        return 'paid';
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
     * @param array<string, mixed> $attributes
     */
    public function recordCashTransaction(array $attributes): CashTransaction
    {
        return DB::transaction(function () use ($attributes): CashTransaction {
            $account = Account::query()->lockForUpdate()->findOrFail($attributes['account_id']);
            
            if (empty($attributes['transaction_number'])) {
                $prefix = $attributes['type'] === 'in' ? 'CASH-IN-' : 'CASH-OUT-';
                $attributes['transaction_number'] = $prefix . date('Ym') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
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
