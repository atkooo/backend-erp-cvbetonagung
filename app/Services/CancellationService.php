<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CashTransaction;
use App\Models\DeliveryOrder;
use App\Models\GoodsReceiptNote;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\Rfq;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\SupplierPayable;
use Illuminate\Support\Facades\DB;

/**
 * Service pembatalan dokumen dengan hierarki ketat (tangga dari bawah ke atas).
 *
 * ─── SALES CHAIN ───────────────────────────────────────────────────────────
 *   Payment          → cancel mandiri; mengurangi paid_amount Invoice (bisa billing ulang)
 *   Invoice          → guard: tolak jika ada Payment aktif
 *                      SO tetap aktif → bisa buat Invoice baru
 *   DeliveryOrder    → stock reversal jika status = shipped
 *                      SO tetap aktif → bisa buat DO baru
 *   SalesOrder       → guard: tolak jika ada DO atau Invoice aktif
 *   Quotation        → guard: tolak jika sudah approved (batalkan SO-nya dulu)
 *
 * ─── PURCHASE CHAIN ────────────────────────────────────────────────────────
 *   SupplierPayable  → cancel mandiri
 *   GoodsReceiptNote → stock reversal; PO received_qty dikurangi kembali
 *   PurchaseOrder    → guard: tolak jika ada GRN aktif atau SupplierPayable aktif
 *   RFQ              → guard: tolak jika ada PO aktif
 *   PurchaseRequest  → guard: tolak jika ada RFQ aktif
 *
 * ─── POS ───────────────────────────────────────────────────────────────────
 *   POS Transaction  → cancel sekaligus: DO + Invoice + SO + stock reversal
 */
class CancellationService
{
    // ══════════════════════════════════════════════════════════════════════
    //  SALES CHAIN
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Batalkan satu Payment dan reverse paid_amount di Invoice terkait.
     *
     * Guard:
     *  - Payment sudah cancelled → tolak
     *
     * Efek:
     *  - paid_amount Invoice dikurangi sebesar payment.amount
     *  - status Invoice disesuaikan kembali (unpaid / partial)
     *  - Invoice tetap aktif → bisa dibayar ulang / di-billing ulang
     */
    public function cancelPayment(string $id, string $userId, string $reason = ''): Payment
    {
        return DB::transaction(function () use ($id, $userId, $reason): Payment {
            /** @var Payment $payment */
            $payment = Payment::query()
                ->with('invoice.salesOrder')
                ->lockForUpdate()
                ->whereKey($id)
                ->firstOrFail();

            if ($payment->invoice?->salesOrder?->source === 'pos') {
                abort(422, 'Ini adalah Pembayaran dari transaksi POS. Pembatalan harus dilakukan secara utuh melalui menu POS / Sales Order.');
            }

            abort_if($payment->status === 'cancelled', 422, 'Payment sudah dibatalkan sebelumnya.');

            $cancelMeta = [
                'status' => 'cancelled',
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ];

            // Reverse paid_amount di Invoice jika payment sudah verified
            if ($payment->invoice_id && in_array($payment->status, ['verified', 'pending'], true)) {
                $invoice = Invoice::query()
                    ->lockForUpdate()
                    ->whereKey($payment->invoice_id)
                    ->first();

                if ($invoice && ! $invoice->isCancelled()) {
                    $reversedAmount = max(0, (float) $invoice->paid_amount - (float) $payment->amount);
                    $newInvoiceStatus = $this->resolveInvoiceStatus($reversedAmount, (float) $invoice->total);

                    $invoice->forceFill([
                        'paid_amount' => $reversedAmount,
                        'status' => $newInvoiceStatus,
                    ])->save();
                }

                if ($payment->status === 'verified' && $payment->account_id) {
                    $this->reversePaymentCash($payment, $userId, $reason);
                }
            }

            $payment->forceFill($cancelMeta)->save();

            return $payment->fresh();
        });
    }

    /**
     * Batalkan sebuah Invoice.
     *
     * Guard:
     *  - Invoice sudah cancelled → tolak
     *  - Masih ada Payment aktif (pending/verified) → tolak; cancel payment dulu
     *
     * Efek:
     *  - Invoice di-cancelled
     *  - SalesOrder tetap aktif → bisa buat Invoice baru
     */
    public function cancelInvoice(string $id, string $userId, string $reason = ''): Invoice
    {
        return DB::transaction(function () use ($id, $userId, $reason): Invoice {
            /** @var Invoice $invoice */
            $invoice = Invoice::query()
                ->with(['payments', 'salesOrder'])
                ->lockForUpdate()
                ->whereKey($id)
                ->firstOrFail();

            if ($invoice->salesOrder?->source === 'pos') {
                abort(422, 'Ini adalah Invoice dari transaksi POS. Pembatalan tidak bisa dilakukan parsial, silakan batalkan seluruh transaksi melalui menu POS / Sales Order.');
            }

            abort_if($invoice->isCancelled(), 422, 'Invoice sudah dibatalkan sebelumnya.');

            $activePayments = $invoice->payments
                ->filter(fn (Payment $p) => ! in_array($p->status, ['cancelled', 'failed'], true));

            abort_if(
                $activePayments->isNotEmpty(),
                422,
                'Tidak dapat membatalkan Invoice karena masih ada '.
                $activePayments->count().' pembayaran aktif. Batalkan semua Payment terlebih dahulu.'
            );

            $invoice->forceFill([
                'status' => 'cancelled',
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ])->save();

            return $invoice->fresh();
        });
    }

    /**
     * Batalkan Delivery Order.
     *
     * Guard:
     *  - DO sudah cancelled → tolak
     *
     * Efek (stock reversal):
     *  - Jika status = shipped → stok item dikembalikan ke lokasi asal (StockMovement type=in)
     *  - SalesOrder tetap aktif → bisa buat DO baru
     */
    public function cancelDeliveryOrder(string $id, string $userId, string $reason = ''): DeliveryOrder
    {
        return DB::transaction(function () use ($id, $userId, $reason): DeliveryOrder {
            /** @var DeliveryOrder $do */
            $do = DeliveryOrder::query()
                ->with(['items', 'salesOrder'])
                ->lockForUpdate()
                ->whereKey($id)
                ->firstOrFail();

            if ($do->salesOrder?->source === 'pos') {
                abort(422, 'Ini adalah Delivery Order dari transaksi POS. Pembatalan tidak bisa dilakukan parsial, silakan batalkan seluruh transaksi melalui menu POS / Sales Order.');
            }

            abort_if($do->isCancelled(), 422, 'Delivery order sudah dibatalkan sebelumnya.');

            // Stock reversal hanya jika DO sudah dikirim (stok sudah keluar)
            if ($do->status === 'shipped') {
                $this->reverseDeliveryOrderStock($do, $userId, $reason);
            }

            $do->forceFill([
                'status' => 'cancelled',
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ])->save();

            return $do->fresh();
        });
    }

    /**
     * Batalkan Sales Order.
     *
     * Guard:
     *  - SO sudah cancelled → tolak
     *  - Masih ada Delivery Order aktif → tolak; cancel DO dulu
     *  - Masih ada Invoice aktif → tolak; cancel Invoice dulu
     *
     * Efek:
     *  - SO di-cancelled
     *  - DO & Invoice harus sudah cancelled sebelumnya (user urus sendiri)
     */
    public function cancelSalesOrder(string $id, string $userId, string $reason = ''): SalesOrder
    {
        return DB::transaction(function () use ($id, $userId, $reason): SalesOrder {
            /** @var SalesOrder $so */
            $so = SalesOrder::query()
                ->with(['deliveryOrders', 'invoices'])
                ->lockForUpdate()
                ->whereKey($id)
                ->firstOrFail();

            if ($so->source === 'pos') {
                return $this->cancelPosTransaction($id, $userId, $reason);
            }

            abort_if($so->isCancelled(), 422, 'Sales order sudah dibatalkan sebelumnya.');

            $activeDeliveries = $so->deliveryOrders
                ->filter(fn (DeliveryOrder $d) => ! $d->isCancelled());

            abort_if(
                $activeDeliveries->isNotEmpty(),
                422,
                'Tidak dapat membatalkan Sales Order karena masih ada '.
                $activeDeliveries->count().' Delivery Order aktif. Batalkan semua DO terlebih dahulu.'
            );

            $activeInvoices = $so->invoices
                ->filter(fn (Invoice $inv) => ! $inv->isCancelled());

            abort_if(
                $activeInvoices->isNotEmpty(),
                422,
                'Tidak dapat membatalkan Sales Order karena masih ada '.
                $activeInvoices->count().' Invoice aktif. Batalkan semua Invoice terlebih dahulu.'
            );

            $so->forceFill([
                'status' => 'cancelled',
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ])->save();

            return $so->fresh();
        });
    }

    /**
     * Batalkan Quotation.
     *
     * Guard:
     *  - Quotation sudah cancelled → tolak
     *  - Status approved → tolak; batalkan Sales Order-nya dulu
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
                'Quotation yang sudah disetujui tidak dapat dibatalkan langsung. Batalkan Sales Order-nya terlebih dahulu.'
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
     * Batalkan transaksi POS sekaligus (khusus source = pos).
     *
     * Guard:
     *  - SO sudah cancelled → tolak
     *  - SO bukan source pos → tolak (gunakan cancel SO biasa)
     *
     * Efek (berurutan):
     *  1. Cancel semua Payment → reverse paid_amount
     *  2. Cancel semua Invoice
     *  3. Cancel semua DO → stock reversal jika shipped
     *  4. Cancel SO
     */
    public function cancelPosTransaction(string $soId, string $userId, string $reason = ''): SalesOrder
    {
        return DB::transaction(function () use ($soId, $userId, $reason): SalesOrder {
            /** @var SalesOrder $so */
            $so = SalesOrder::query()
                ->with(['deliveryOrders.items', 'invoices.payments'])
                ->lockForUpdate()
                ->whereKey($soId)
                ->firstOrFail();

            abort_if($so->isCancelled(), 422, 'Transaksi POS sudah dibatalkan sebelumnya.');
            abort_if($so->source !== 'pos', 422, 'Endpoint ini hanya untuk membatalkan transaksi POS.');

            $cancelMeta = [
                'status' => 'cancelled',
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ];

            // 1. Cancel semua Payment → reverse paid_amount invoice dan reverse cash
            foreach ($so->invoices as $invoice) {
                foreach ($invoice->payments as $payment) {
                    if (! in_array($payment->status, ['cancelled', 'failed'], true)) {
                        $this->reversePaymentCash($payment, $userId, $reason);
                        $payment->forceFill($cancelMeta)->save();
                    }
                }

                // Reset paid_amount invoice ke 0 setelah semua payment di-cancel
                if (! $invoice->isCancelled()) {
                    $invoice->forceFill([
                        'paid_amount' => 0,
                        'status' => 'cancelled',
                        'cancelled_by' => $userId,
                        'cancelled_at' => now(),
                        'cancel_reason' => $reason,
                    ])->save();
                }
            }

            // 2. Cancel semua DO + stock reversal
            foreach ($so->deliveryOrders as $do) {
                if (! $do->isCancelled()) {
                    if ($do->status === 'shipped') {
                        $this->reverseDeliveryOrderStock($do, $userId, $reason);
                    }
                    $do->forceFill($cancelMeta)->save();
                }
            }

            // 3. Reverse stock untuk take_away POS (StockMovement type=pos, reference_type=pos)
            $this->reversePosDirectStockDeductions($so, $userId, $reason);

            // 4. Cancel SO
            $so->forceFill($cancelMeta)->save();

            return $so->fresh();
        });
    }

    // ══════════════════════════════════════════════════════════════════════
    //  PURCHASE CHAIN
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Batalkan Goods Receipt Note dan reverse stok yang pernah masuk.
     *
     * Guard:
     *  - GRN sudah cancelled → tolak
     *
     * Efek:
     *  - Stok setiap item GRN dikurangi kembali (StockMovement type=out reversal)
     *  - received_qty di PurchaseOrderItem dikurangi kembali
     *  - Status PO disesuaikan (kembali ke pending/partially_received)
     *  - PO tetap aktif → bisa terima ulang
     */
    public function cancelGoodsReceiptNote(string $id, string $userId, string $reason = ''): GoodsReceiptNote
    {
        return DB::transaction(function () use ($id, $userId, $reason): GoodsReceiptNote {
            /** @var GoodsReceiptNote $grn */
            $grn = GoodsReceiptNote::query()
                ->with(['items.purchaseOrderItem', 'purchaseOrder.items'])
                ->lockForUpdate()
                ->whereKey($id)
                ->firstOrFail();

            abort_if($grn->isCancelled(), 422, 'Goods Receipt Note sudah dibatalkan sebelumnya.');

            // Reverse stok per item GRN
            foreach ($grn->items as $grnItem) {
                $qtyReversed = (float) $grnItem->received_qty;

                if ($qtyReversed <= 0) {
                    continue;
                }

                // Kurangi stok di lokasi tujuan penerimaan
                $stock = ProductStock::query()
                    ->where('product_id', $grnItem->product_id)
                    ->where('location_id', $grn->to_location_id)
                    ->lockForUpdate()
                    ->first();

                if ($stock) {
                    $newQty = max(0, (float) $stock->quantity - $qtyReversed);
                    $stock->forceFill(['quantity' => $newQty])->save();
                }

                // Catat StockMovement reversal (out)
                StockMovement::query()->create([
                    'product_id' => $grnItem->product_id,
                    'from_location_id' => $grn->to_location_id,
                    'to_location_id' => null,
                    'type' => 'out',
                    'quantity' => $qtyReversed,
                    'reference_type' => 'grn_reversal',
                    'reference_id' => $grn->id,
                    'reference_number' => $grn->grn_number,
                    'handled_by' => $userId,
                    'notes' => 'Reversal pembatalan GRN: '.$grn->grn_number.' — '.$reason,
                    'movement_at' => now(),
                ]);

                // Kurangi received_qty di PurchaseOrderItem
                if ($grnItem->purchaseOrderItem) {
                    $poItem = $grnItem->purchaseOrderItem;
                    $newReceivedQty = max(0, (float) $poItem->received_qty - $qtyReversed);
                    $poItem->forceFill(['received_qty' => $newReceivedQty])->save();
                }
            }

            // Sesuaikan status PO setelah reversal
            if ($grn->purchase_order_id) {
                $this->recalculatePurchaseOrderStatus($grn->purchase_order_id);
            }

            $grn->forceFill([
                'status' => 'cancelled',
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ])->save();

            return $grn->fresh();
        });
    }

    /**
     * Batalkan Purchase Order.
     *
     * Guard:
     *  - PO sudah cancelled → tolak
     *  - Masih ada GRN aktif → tolak; cancel GRN dulu (agar stok sudah di-reverse)
     *  - Masih ada SupplierPayable aktif yang belum cancelled → tolak
     *
     * Efek:
     *  - PO di-cancelled
     */
    public function cancelPurchaseOrder(string $id, string $userId, string $reason = ''): PurchaseOrder
    {
        return DB::transaction(function () use ($id, $userId, $reason): PurchaseOrder {
            /** @var PurchaseOrder $po */
            $po = PurchaseOrder::query()
                ->with(['goodsReceiptNotes', 'supplierPayables'])
                ->lockForUpdate()
                ->whereKey($id)
                ->firstOrFail();

            abort_if($po->isCancelled(), 422, 'Purchase order sudah dibatalkan sebelumnya.');

            $activeGrns = $po->goodsReceiptNotes
                ->filter(fn (GoodsReceiptNote $g) => ! $g->isCancelled());

            abort_if(
                $activeGrns->isNotEmpty(),
                422,
                'Tidak dapat membatalkan PO karena masih ada '.
                $activeGrns->count().' Goods Receipt Note aktif. Batalkan semua GRN terlebih dahulu.'
            );

            $activePayables = $po->supplierPayables
                ->filter(fn (SupplierPayable $p) => ! $p->isCancelled());

            abort_if(
                $activePayables->isNotEmpty(),
                422,
                'Tidak dapat membatalkan PO karena masih ada '.
                $activePayables->count().' Supplier Payable aktif. Batalkan semua Payable terlebih dahulu.'
            );

            $po->forceFill([
                'status' => 'cancelled',
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ])->save();

            return $po->fresh();
        });
    }

    /**
     * Batalkan SupplierPayable secara mandiri.
     *
     * Guard:
     *  - Payable sudah cancelled → tolak
     */
    public function cancelSupplierPayable(string $id, string $userId, string $reason = ''): SupplierPayable
    {
        return DB::transaction(function () use ($id, $userId, $reason): SupplierPayable {
            /** @var SupplierPayable $payable */
            $payable = SupplierPayable::query()
                ->lockForUpdate()
                ->whereKey($id)
                ->firstOrFail();

            abort_if($payable->isCancelled(), 422, 'Supplier Payable sudah dibatalkan sebelumnya.');

            $this->reverseSupplierPayableCash($payable, $userId, $reason);

            $payable->forceFill([
                'status' => 'cancelled',
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ])->save();

            return $payable->fresh();
        });
    }

    /**
     * Batalkan RFQ.
     *
     * Guard:
     *  - RFQ sudah cancelled → tolak
     *  - Masih ada PO aktif → tolak; cancel PO dulu
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

            $activePOs = $rfq->purchaseOrders
                ->filter(fn (PurchaseOrder $po) => ! $po->isCancelled());

            abort_if(
                $activePOs->isNotEmpty(),
                422,
                'Tidak dapat membatalkan RFQ karena masih ada '.
                $activePOs->count().' Purchase Order aktif. Batalkan semua PO terlebih dahulu.'
            );

            $rfq->forceFill([
                'status' => 'cancelled',
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ])->save();

            return $rfq->fresh();
        });
    }

    /**
     * Batalkan Purchase Request.
     *
     * Guard:
     *  - PR sudah cancelled → tolak
     *  - Masih ada RFQ aktif → tolak; cancel RFQ dulu
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

            $activeRfqs = Rfq::where('purchase_request_id', $pr->id)
                ->where('status', '!=', 'cancelled')
                ->count();

            abort_if(
                $activeRfqs > 0,
                422,
                'Tidak dapat membatalkan PR karena masih ada '.$activeRfqs.' RFQ aktif. Batalkan semua RFQ terlebih dahulu.'
            );

            // Periksa juga PO yang langsung terkait PR (tanpa via RFQ)
            $activePOs = PurchaseOrder::where('purchase_request_id', $pr->id)
                ->where('status', '!=', 'cancelled')
                ->count();

            abort_if(
                $activePOs > 0,
                422,
                'Tidak dapat membatalkan PR karena masih ada '.$activePOs.' Purchase Order aktif. Batalkan semua PO terlebih dahulu.'
            );

            $pr->forceFill([
                'status' => 'cancelled',
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ])->save();

            return $pr->fresh();
        });
    }

    // ══════════════════════════════════════════════════════════════════════
    //  Private helpers
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Kembalikan stok untuk semua item DO yang sudah shipped.
     * Dipanggil dari cancelDeliveryOrder() dan cancelPosTransaction().
     */
    private function reverseDeliveryOrderStock(DeliveryOrder $do, string $userId, string $reason): void
    {
        foreach ($do->items as $item) {
            $qty = (float) $item->quantity;

            if ($qty <= 0) {
                continue;
            }

            // Tambahkan kembali stok ke lokasi asal (cari StockMovement terkait untuk tahu lokasi)
            $originalMovement = StockMovement::query()
                ->where('reference_type', 'delivery_order')
                ->where('reference_id', $do->id)
                ->where('product_id', $item->product_id)
                ->where('type', 'out')
                ->first();

            $fromLocationId = $originalMovement?->from_location_id;

            if ($fromLocationId) {
                $stock = ProductStock::query()
                    ->firstOrCreate(
                        ['product_id' => $item->product_id, 'location_id' => $fromLocationId],
                        ['quantity' => 0]
                    );
                $stock->increment('quantity', $qty);
            }

            // Catat StockMovement reversal (in)
            StockMovement::query()->create([
                'product_id' => $item->product_id,
                'from_location_id' => null,
                'to_location_id' => $fromLocationId,
                'type' => 'in',
                'quantity' => $qty,
                'reference_type' => 'do_reversal',
                'reference_id' => $do->id,
                'reference_number' => $do->delivery_number,
                'handled_by' => $userId,
                'notes' => 'Reversal pembatalan DO: '.$do->delivery_number.' — '.$reason,
                'movement_at' => now(),
            ]);
        }
    }

    /**
     * Reverse stok pemotongan langsung POS (take_away).
     * Mencari StockMovement dengan reference_type = 'pos' untuk SO ini.
     */
    private function reversePosDirectStockDeductions(SalesOrder $so, string $userId, string $reason): void
    {
        $posMovements = StockMovement::query()
            ->where('reference_type', 'pos')
            ->where('reference_id', $so->id)
            ->where('type', 'out')
            ->get();

        foreach ($posMovements as $movement) {
            $qty = (float) $movement->quantity;

            if ($qty <= 0) {
                continue;
            }

            $toLocationId = $movement->from_location_id;

            if ($toLocationId) {
                $stock = ProductStock::query()
                    ->firstOrCreate(
                        ['product_id' => $movement->product_id, 'location_id' => $toLocationId],
                        ['quantity' => 0]
                    );
                $stock->increment('quantity', $qty);
            }

            StockMovement::query()->create([
                'product_id' => $movement->product_id,
                'from_location_id' => null,
                'to_location_id' => $toLocationId,
                'type' => 'in',
                'quantity' => $qty,
                'reference_type' => 'pos_reversal',
                'reference_id' => $so->id,
                'reference_number' => $so->order_number,
                'handled_by' => $userId,
                'notes' => 'Reversal pembatalan POS: '.$so->order_number.' — '.$reason,
                'movement_at' => now(),
            ]);
        }
    }

    /**
     * Hitung ulang status PO setelah GRN di-cancel (received_qty berubah).
     */
    private function recalculatePurchaseOrderStatus(string $purchaseOrderId): void
    {
        $po = PurchaseOrder::query()->with('items')->whereKey($purchaseOrderId)->first();

        if (! $po || $po->isCancelled()) {
            return;
        }

        $totalQty = 0.0;
        $totalReceivedQty = 0.0;

        foreach ($po->items as $item) {
            $totalQty += (float) $item->quantity;
            $totalReceivedQty += (float) $item->received_qty;
        }

        $newStatus = match (true) {
            $totalReceivedQty <= 0 => 'pending',
            $totalReceivedQty >= $totalQty => 'fully_received',
            default => 'partially_received',
        };

        $po->forceFill(['status' => $newStatus])->save();
    }

    /**
     * Hitung status Invoice berdasarkan paid_amount vs total.
     */
    private function resolveInvoiceStatus(float $paidAmount, float $total): string
    {
        if ($paidAmount <= 0) {
            return 'unpaid';
        }

        return $paidAmount >= $total ? 'paid' : 'partial';
    }

    /**
     * Kembalikan uang (reverse cash) dari Payment yang dibatalkan.
     */
    private function reversePaymentCash(Payment $payment, string $userId, string $reason): void
    {
        if ($payment->status === 'verified' && $payment->account_id) {
            $account = Account::query()->lockForUpdate()->find($payment->account_id);
            if ($account) {
                $transactionNumber = 'CASH-OUT-'.date('Ym').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
                CashTransaction::create([
                    'account_id' => $payment->account_id,
                    'type' => 'out',
                    'amount' => $payment->amount,
                    'transaction_date' => now()->toDateString(),
                    'category' => 'expense',
                    'reference_type' => 'App\Models\Payment',
                    'reference_id' => $payment->id,
                    'transaction_number' => $transactionNumber,
                    'description' => 'Pembatalan pembayaran faktur '.($payment->invoice?->invoice_number ?? $payment->invoice_id).' — '.$reason,
                    'recorded_by' => $userId,
                ]);
                $account->balance -= $payment->amount;
                $account->save();
            }
        }
    }

    /**
     * Kembalikan uang (reverse cash) dari SupplierPayable yang dibatalkan.
     * Mencari semua transaksi kas keluar terkait hutang ini, lalu me-reverse dengan kas masuk.
     */
    private function reverseSupplierPayableCash(SupplierPayable $payable, string $userId, string $reason): void
    {
        $transactions = CashTransaction::query()
            ->where('reference_type', 'App\Models\SupplierPayable')
            ->where('reference_id', $payable->id)
            ->where('type', 'out')
            ->get();

        foreach ($transactions as $tx) {
            $account = Account::query()->lockForUpdate()->find($tx->account_id);
            if ($account) {
                $transactionNumber = 'CASH-IN-'.date('Ym').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
                CashTransaction::create([
                    'account_id' => $tx->account_id,
                    'type' => 'in',
                    'amount' => $tx->amount,
                    'transaction_date' => now()->toDateString(),
                    'category' => 'revenue',
                    'reference_type' => 'App\Models\SupplierPayable',
                    'reference_id' => $payable->id,
                    'transaction_number' => $transactionNumber,
                    'description' => 'Pengembalian kas dari pembatalan hutang '.($payable->payable_number ?? $payable->id).' — '.$reason,
                    'recorded_by' => $userId,
                ]);
                $account->balance += $tx->amount;
                $account->save();
            }
        }
    }
}
