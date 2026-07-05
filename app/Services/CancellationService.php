<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\Rfq;
use App\Models\SalesOrder;
use App\Models\SupplierPayable;
use Illuminate\Support\Facades\DB;

/**
 * Service untuk pembatalan dokumen dengan cascading.
 *
 * Hierarki:
 *   SalesOrder → DeliveryOrder (semua status) + Invoice (semua status) → Payment (semua status)
 *   PurchaseOrder → SupplierPayable (semua status)
 *   Invoice → Payment (semua status)
 *   Quotation / DeliveryOrder → hanya status sendiri
 *
 * Catatan: pembatalan tetap bersifat runtut (cascade), tidak ada guard berdasarkan status dokumen.
 * Satu-satunya yang diblokir adalah jika dokumen SUDAH berstatus cancelled (tidak perlu cancel ulang).
 */
class CancellationService
{
    /**
     * Batalkan Sales Order beserta SELURUH turunannya (cascade penuh).
     *
     * Guard:
     *  - SO sudah cancelled → tolak (tidak perlu cancel ulang)
     *
     * Cascade:
     *  - SEMUA Delivery Order (ready_to_load, shipped, received) → cancelled
     *  - SEMUA Invoice (unpaid, partial, overdue, paid/Lunas) → cancelled
     *    └─ SEMUA Payment (pending, Verified) milik Invoice → cancelled
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

            $cancelMeta = [
                'status' => 'cancelled',
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ];

            // Cancel SEMUA DO (termasuk yang sudah shipped/received)
            foreach ($so->deliveryOrders as $do) {
                if (! $do->isCancelled()) {
                    $do->forceFill($cancelMeta)->save();
                }
            }

            // Cancel SEMUA Invoice + SEMUA Payment turunannya
            foreach ($so->invoices as $invoice) {
                if (! $invoice->isCancelled()) {
                    $this->cancelInvoiceRecord($invoice, $userId, $reason);
                }
            }

            // Cancel SO sendiri
            $so->forceFill($cancelMeta)->save();

            return $so->fresh();
        });
    }

    /**
     * Batalkan sebuah Invoice beserta SEMUA Payment miliknya (cascade penuh).
     *
     * Guard:
     *  - Invoice sudah cancelled → tolak
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

            $this->cancelInvoiceRecord($invoice, $userId, $reason);

            return $invoice->fresh();
        });
    }

    /**
     * Batalkan Purchase Request beserta SEMUA turunan (RFQ, PO, Payable).
     *
     * Guard:
     *  - PR sudah cancelled → tolak
     */
    public function cancelPurchaseRequest(string $id, string $userId, string $reason = ''): PurchaseRequest
    {
        return DB::transaction(function () use ($id, $userId, $reason): PurchaseRequest {
            /** @var PurchaseRequest $pr */
            $pr = PurchaseRequest::query()
                ->lockForUpdate()
                ->whereKey($id)
                ->firstOrFail();

            abort_if($pr->isCancelled(), 422, 'Purchase Request sudah dibatalkan sebelumnya.');

            $cancelMeta = [
                'status' => 'cancelled',
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ];

            // Cancel terkait RFQ (asumsikan relation ada)
            $rfqs = Rfq::where('purchase_request_id', $pr->id)->get();
            foreach ($rfqs as $rfq) {
                if (! $rfq->isCancelled()) {
                    $this->cancelRfq($rfq->id, $userId, $reason);
                }
            }

            // Cancel langsung terkait PO (bisa dari PR langsung)
            $pos = PurchaseOrder::where('purchase_request_id', $pr->id)->get();
            foreach ($pos as $po) {
                if (! $po->isCancelled()) {
                    $this->cancelPurchaseOrder($po->id, $userId, $reason);
                }
            }

            $pr->forceFill($cancelMeta)->save();

            return $pr->fresh();
        });
    }

    /**
     * Batalkan RFQ beserta SEMUA turunan (PO, Payable).
     *
     * Guard:
     *  - RFQ sudah cancelled → tolak
     */
    public function cancelRfq(string $id, string $userId, string $reason = ''): Rfq
    {
        return DB::transaction(function () use ($id, $userId, $reason): Rfq {
            /** @var Rfq $rfq */
            $rfq = Rfq::query()
                ->with('purchaseOrders')
                ->lockForUpdate()
                ->whereKey($id)
                ->firstOrFail();

            abort_if($rfq->isCancelled(), 422, 'RFQ sudah dibatalkan sebelumnya.');

            $cancelMeta = [
                'status' => 'cancelled',
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ];

            foreach ($rfq->purchaseOrders as $po) {
                if (! $po->isCancelled()) {
                    $this->cancelPurchaseOrder($po->id, $userId, $reason);
                }
            }

            $rfq->forceFill($cancelMeta)->save();

            return $rfq->fresh();
        });
    }

    /**
     * Batalkan Purchase Order beserta SEMUA SupplierPayable miliknya.
     *
     * Guard:
     *  - PO sudah cancelled → tolak
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

            $cancelMeta = [
                'status' => 'cancelled',
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ];

            // Cancel SEMUA SupplierPayable (open, partial, paid)
            foreach ($po->supplierPayables as $payable) {
                if (! $payable->isCancelled()) {
                    $payable->forceFill($cancelMeta)->save();
                }
            }

            $po->forceFill($cancelMeta)->save();

            return $po->fresh();
        });
    }

    /**
     * Batalkan Quotation (tidak ada turunan yang perlu di-cascade).
     *
     * Guard:
     *  - Quotation sudah cancelled → tolak
     *  - Status approved → tolak (batalkan lewat Sales Order-nya)
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
     *
     * Guard:
     *  - DO sudah cancelled → tolak
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

    /**
     * Internal: batalkan Invoice + SEMUA Payment miliknya (tanpa cek status invoice).
     * Digunakan baik dari cancelInvoice() maupun cascade dari cancelSalesOrder().
     */
    private function cancelInvoiceRecord(Invoice $invoice, string $userId, string $reason): void
    {
        $cancelMeta = [
            'status' => 'cancelled',
            'cancelled_by' => $userId,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ];

        // Cancel SEMUA payment milik invoice ini (pending maupun Verified)
        Payment::query()
            ->where('invoice_id', $invoice->id)
            ->whereNotIn('status', ['cancelled'])
            ->update($cancelMeta);

        $invoice->forceFill($cancelMeta)->save();
    }
}
