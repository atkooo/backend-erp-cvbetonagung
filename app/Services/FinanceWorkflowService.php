<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\CashTransaction;
use App\Models\Account;
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

            return $payment;
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
     * @param array<string, mixed> $attributes
     */
    public function recordCashTransaction(array $attributes): CashTransaction
    {
        return DB::transaction(function () use ($attributes): CashTransaction {
            $account = Account::query()->lockForUpdate()->findOrFail($attributes['account_id']);
            
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
