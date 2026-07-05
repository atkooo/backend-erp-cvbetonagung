<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SupplierPayable;
use Illuminate\Support\Facades\DB;

/**
 * Service untuk pembatalan dokumen dengan cascading.
 *
 * Hierarki:
 *   SalesOrder → DeliveryOrder (non-shipped) + Invoice (unpaid/partial) → Payment (pending)
 *   PurchaseOrder → SupplierPayable (open/partial)
 *   Invoice → Payment (pending)
 *   Quotation / DeliveryOrder → hanya status sendiri
 */
class CancellationService
{
    /**
     * Batalkan Sales Order beserta turunannya.
     *
     * Guard:
     *  - SO sudah cancelled → skip
     *  - Invoice sudah paid → abort (tidak bisa cancel)
     *  - DO sudah shipped/received → skip (bukan abort), karena barang sudah jalan
     */
    public function cancelSalesOrder(string $id, string $userId, string $reason = ''): SalesOrder
    {
        return DB::transaction(function () use ($id, $userId, $reason): SalesOrder {
            /** @var SalesOrder $so */
            $so = SalesOrder::query()
                ->with(['invoices', 'deliveryOrders'])
                ->lockForUpdate()
                ->whereKey($id)
                ->firstOrFail();

            abort_if($so->isCancelled(), 422, 'Sales order sudah dibatalkan sebelumnya.');

            // Guard: ada invoice yang sudah paid?
            $paidInvoice = $so->invoices->first(fn ($inv) => $inv->status === 'paid');
            if ($paidInvoice) {
                abort(422, "Sales order tidak dapat dibatalkan karena invoice {$paidInvoice->invoice_number} sudah berstatus 'paid'.");
            }

            $cancelMeta = [
                'status' => 'cancelled',
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ];

            // Cancel DO yang belum shipped / received
            $cancellableStatuses = ['ready_to_load'];
            foreach ($so->deliveryOrders as $do) {
                if (in_array($do->status, $cancellableStatuses, true)) {
                    $do->forceFill($cancelMeta)->save();
                }
            }

            // Cancel Invoice + Payment turunannya
            foreach ($so->invoices as $invoice) {
                if (in_array($invoice->status, ['unpaid', 'partial', 'overdue'], true)) {
                    $this->cancelInvoiceRecord($invoice, $userId, $reason);
                }
            }

            // Cancel SO sendiri
            $so->forceFill($cancelMeta)->save();

            return $so->fresh();
        });
    }

    /**
     * Batalkan sebuah Invoice beserta Payment yang masih pending.
     */
    public function cancelInvoice(string $id, string $userId, string $reason = ''): Invoice
    {
        return DB::transaction(function () use ($id, $userId, $reason): Invoice {
            /** @var Invoice $invoice */
            $invoice = Invoice::query()
                ->with('payments')
                ->lockForUpdate()
                ->whereKey($id)
                ->firstOrFail();

            abort_if($invoice->isCancelled(), 422, 'Invoice sudah dibatalkan sebelumnya.');
            abort_if($invoice->status === 'paid', 422, 'Invoice yang sudah lunas tidak dapat dibatalkan.');

            $this->cancelInvoiceRecord($invoice, $userId, $reason);

            return $invoice->fresh();
        });
    }

    /**
     * Batalkan Purchase Order beserta SupplierPayable yang belum lunas.
     */
    public function cancelPurchaseOrder(string $id, string $userId, string $reason = ''): PurchaseOrder
    {
        return DB::transaction(function () use ($id, $userId, $reason): PurchaseOrder {
            /** @var PurchaseOrder $po */
            $po = PurchaseOrder::query()
                ->with('supplierPayables')
                ->lockForUpdate()
                ->whereKey($id)
                ->firstOrFail();

            abort_if($po->isCancelled(), 422, 'Purchase order sudah dibatalkan sebelumnya.');

            // Guard: ada payable yang sudah paid?
            $paidPayable = $po->supplierPayables->first(fn ($p) => $p->status === 'paid');
            if ($paidPayable) {
                abort(422, "Purchase order tidak dapat dibatalkan karena hutang {$paidPayable->payable_number} sudah berstatus 'paid'.");
            }

            $cancelMeta = [
                'status' => 'cancelled',
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ];

            foreach ($po->supplierPayables as $payable) {
                if (in_array($payable->status, ['open', 'partial'], true)) {
                    $payable->forceFill($cancelMeta)->save();
                }
            }

            $po->forceFill($cancelMeta)->save();

            return $po->fresh();
        });
    }

    /**
     * Batalkan Quotation (tidak ada turunan yang perlu di-cascade).
     */
    public function cancelQuotation(string $id, string $userId, string $reason = ''): Quotation
    {
        return DB::transaction(function () use ($id, $userId, $reason): Quotation {
            /** @var Quotation $quotation */
            $quotation = Quotation::query()
                ->lockForUpdate()
                ->whereKey($id)
                ->firstOrFail();

            abort_if($quotation->isCancelled(), 422, 'Quotation sudah dibatalkan sebelumnya.');
            abort_if(
                $quotation->status === 'approved',
                422,
                'Quotation yang sudah disetujui tidak dapat dibatalkan. Batalkan Sales Order-nya.'
            );

            $quotation->forceFill([
                'status' => 'cancelled',
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ])->save();

            return $quotation->fresh();
        });
    }

    /**
     * Batalkan Delivery Order secara mandiri (bukan via cascade SO).
     */
    public function cancelDeliveryOrder(string $id, string $userId, string $reason = ''): DeliveryOrder
    {
        return DB::transaction(function () use ($id, $userId, $reason): DeliveryOrder {
            /** @var DeliveryOrder $do */
            $do = DeliveryOrder::query()
                ->lockForUpdate()
                ->whereKey($id)
                ->firstOrFail();

            abort_if($do->isCancelled(), 422, 'Delivery order sudah dibatalkan sebelumnya.');
            abort_if(
                in_array($do->status, ['shipped', 'received'], true),
                422,
                'Delivery order yang sudah dikirim/diterima tidak dapat dibatalkan.'
            );

            $do->forceFill([
                'status' => 'cancelled',
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ])->save();

            return $do->fresh();
        });
    }

    // ── Private helpers ───────────────────────────────────────

    private function cancelInvoiceRecord(Invoice $invoice, string $userId, string $reason): void
    {
        $cancelMeta = [
            'status' => 'cancelled',
            'cancelled_by' => $userId,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ];

        // Cancel pending payments
        Payment::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', 'pending')
            ->update($cancelMeta);

        $invoice->forceFill($cancelMeta)->save();
    }
}
