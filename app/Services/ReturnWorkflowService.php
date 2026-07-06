<?php

namespace App\Services;

use App\Enums\DeliveryOrderStatus;
use App\Models\CashTransaction;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Invoice;
use App\Models\ProductReturn;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReturnItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\SupplierPayable;
use Illuminate\Support\Facades\DB;

class ReturnWorkflowService
{
    public function approveReturn(string $id, bool $allowBackorder = false): ProductReturn
    {
        return DB::transaction(function () use ($id, $allowBackorder): ProductReturn {
            $return = ProductReturn::query()->with('items.product')->findOrFail($id);

            abort_if($return->qc_status === 'approved', 422, 'Return is already approved.');

            if ($return->type === 'customer' && $return->action === 'replace' && !$allowBackorder) {
                foreach ($return->items as $item) {
                    $stock = ProductStock::where('product_id', $item->product_id)->sum('quantity');
                    $available = (float) $stock;
                    $required = (float) $item->quantity;
                    abort_if($available < $required, 422, "Stok tidak mencukupi untuk barang pengganti ({$item->product->name}). Tersedia: {$available}, Dibutuhkan: {$required}");
                }
            }

            $return->qc_status = 'approved';
            $return->save();

            $location = StorageLocation::first();
            $locationId = $location ? $location->id : null;

            foreach ($return->items as $item) {
                if ($return->type === 'customer') {
                    $this->approveCustomerReturnItem($return, $item, $locationId);
                } elseif ($return->type === 'supplier') {
                    $this->approveSupplierReturnItem($return, $item);
                }
            }

            if ($return->type === 'customer' && $return->sales_order_id) {
                if ($return->action === 'replace') {
                    $this->createReplacementDeliveryOrder($return);
                } else {
                    $this->processAutoRefundIfPos($return);
                }
            }

            if ($return->type === 'supplier' && $return->purchase_order_id) {
                if ($return->action === 'replace') {
                    $this->createReplacementPurchaseOrder($return);
                }
            }

            return $return;
        });
    }

    private function resolveQuarantineLocationId(): ?string
    {
        $warehouse = \App\Models\Warehouse::query()->first();
        if (!$warehouse) {
            return null;
        }

        $quarantineLocation = \App\Models\StorageLocation::query()->firstOrCreate(
            ['code' => 'LOC-KARANTINA'],
            [
                'warehouse_id' => $warehouse->id,
                'name' => 'GUDANG KARANTINA RETUR',
                'description' => 'Lokasi penampungan sementara barang retur sebelum diputuskan layak jual atau rusak'
            ]
        );

        return $quarantineLocation->id;
    }

    private function approveCustomerReturnItem(ProductReturn $return, ReturnItem $item, ?string $defaultLocationId): void
    {
        $locationId = $this->resolveQuarantineLocationId() ?? $defaultLocationId;

        $stock = ProductStock::query()->firstOrCreate(
            ['product_id' => $item->product_id, 'location_id' => $locationId],
            ['quantity' => 0]
        );
        $stock->increment('quantity', $item->quantity);

        StockMovement::query()->create([
            'product_id' => $item->product_id,
            'from_location_id' => null,
            'to_location_id' => $locationId,
            'type' => 'in',
            'quantity' => $item->quantity,
            'reference_type' => 'return',
            'reference_id' => $return->id,
            'reference_number' => $return->return_number,
            'notes' => 'Customer Return Approved',
            'movement_at' => now(),
        ]);

        if (! $return->sales_order_id) {
            return;
        }

        $invoice = Invoice::where('sales_order_id', $return->sales_order_id)->first();
        if (! $invoice) {
            return;
        }

        $soItem = SalesOrderItem::where('sales_order_id', $return->sales_order_id)
            ->where('product_id', $item->product_id)
            ->first();

        if ($soItem && $return->action !== 'replace') {
            $deduction = $item->quantity * $soItem->unit_price;
            $invoice->total = max(0, $invoice->total - $deduction);
            $invoice->status = $invoice->paid_amount >= $invoice->total ? 'paid' : 'partial';
            $invoice->save();
        }
    }

    private function createReplacementDeliveryOrder(ProductReturn $return): void
    {
        $salesOrder = SalesOrder::query()->find($return->sales_order_id);
        if (! $salesOrder) {
            return;
        }

        $do = DeliveryOrder::query()->create([
            'delivery_number' => 'DO-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4)),
            'sales_order_id' => $salesOrder->id,
            'customer_id' => $salesOrder->customer_id,
            'status' => DeliveryOrderStatus::Draft->value,
            'notes' => 'Penggantian Barang Retur: '.$return->return_number,
        ]);

        foreach ($return->items as $item) {
            $soItem = SalesOrderItem::where('sales_order_id', $return->sales_order_id)
                ->where('product_id', $item->product_id)
                ->first();

            if ($soItem) {
                DeliveryOrderItem::query()->create([
                    'delivery_order_id' => $do->id,
                    'sales_order_item_id' => $soItem->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                ]);
            }
        }
    }

    private function processAutoRefundIfPos(ProductReturn $return): void
    {
        $salesOrder = SalesOrder::query()->find($return->sales_order_id);
        if (! $salesOrder || $salesOrder->source !== 'pos') {
            return;
        }

        $invoice = Invoice::query()->where('sales_order_id', $salesOrder->id)->first();
        if (! $invoice) {
            return;
        }

        $overpayment = (float) $invoice->paid_amount - (float) $invoice->total;
        if ($overpayment <= 0) {
            return;
        }

        // Cari transaksi kasir penerimaan uang untuk transaksi POS ini
        $cashInTransaction = CashTransaction::query()
            ->where('reference_type', 'invoice')
            ->where('reference_id', $invoice->id)
            ->where('type', 'in')
            ->first();

        if ($cashInTransaction && $cashInTransaction->account_id) {
            app(FinanceWorkflowService::class)->recordCashTransaction([
                'account_id' => $cashInTransaction->account_id,
                'transaction_date' => now()->toDateString(),
                'type' => 'out',
                'amount' => $overpayment,
                'category' => 'sales', // Atur kategori sebagai penjualan agar rapi di laporan (pengurang)
                'description' => 'Auto-Refund retur kasir POS: '.$return->return_number,
                'reference_type' => 'return',
                'reference_id' => $return->id,
            ]);

            // Seimbangkan nilai paid_amount invoice agar statusnya lunas dan tidak overpaid
            $invoice->forceFill([
                'paid_amount' => $invoice->total,
                'status' => 'paid',
            ])->save();
        }
    }

    public function manualRefundOverpayment(string $id, string $accountId): void
    {
        DB::transaction(function () use ($id, $accountId) {
            $return = ProductReturn::query()->findOrFail($id);
            abort_if($return->type !== 'customer', 422, 'Only customer returns can be refunded.');
            abort_if($return->qc_status !== 'approved', 422, 'Return must be approved first.');
            abort_if(! $return->sales_order_id, 422, 'Return must be linked to a sales order.');

            $invoice = Invoice::query()->where('sales_order_id', $return->sales_order_id)->firstOrFail();
            $overpayment = (float) $invoice->paid_amount - (float) $invoice->total;

            abort_if($overpayment <= 0, 422, 'No overpayment to refund.');

            app(FinanceWorkflowService::class)->recordCashTransaction([
                'account_id' => $accountId,
                'transaction_date' => now()->toDateString(),
                'type' => 'out',
                'amount' => $overpayment,
                'category' => 'sales',
                'description' => 'Manual Refund retur: '.$return->return_number,
                'reference_type' => 'return',
                'reference_id' => $return->id,
            ]);

            $invoice->forceFill([
                'paid_amount' => $invoice->total,
                'status' => 'paid',
            ])->save();
        });
    }

    private function approveSupplierReturnItem(ProductReturn $return, ReturnItem $item): void
    {
        $qtyToCut = $item->quantity;
        $stocks = ProductStock::query()
            ->where('product_id', $item->product_id)
            ->where('quantity', '>', 0)
            ->lockForUpdate()
            ->get();

        foreach ($stocks as $stock) {
            if ($qtyToCut <= 0) {
                break;
            }

            $cut = min((float) $stock->quantity, $qtyToCut);
            $stock->quantity = (float) $stock->quantity - $cut;
            $stock->save();

            StockMovement::query()->create([
                'product_id' => $item->product_id,
                'from_location_id' => $stock->location_id,
                'to_location_id' => null,
                'type' => 'out',
                'quantity' => $cut,
                'reference_type' => 'return',
                'reference_id' => $return->id,
                'reference_number' => $return->return_number,
                'notes' => 'Supplier Return Approved',
                'movement_at' => now(),
            ]);

            $qtyToCut -= $cut;
        }

        if (! $return->purchase_order_id) {
            return;
        }

        $payable = SupplierPayable::where('purchase_order_id', $return->purchase_order_id)->first();
        if (! $payable) {
            return;
        }

        $poItem = PurchaseOrderItem::where('purchase_order_id', $return->purchase_order_id)
            ->where('product_id', $item->product_id)
            ->first();

        if ($poItem && $return->action !== 'replace') {
            $deduction = $item->quantity * $poItem->unit_price;
            $payable->amount = max(0, $payable->amount - $deduction);
            $payable->status = $payable->paid_amount >= $payable->amount ? 'paid' : 'open';
            $payable->save();
        }
    }

    private function createReplacementPurchaseOrder(ProductReturn $return): void
    {
        $purchaseOrder = PurchaseOrder::query()->find($return->purchase_order_id);
        if (! $purchaseOrder) {
            return;
        }

        $po = PurchaseOrder::query()->create([
            'po_number' => 'PO-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4)),
            'supplier_id' => $purchaseOrder->supplier_id,
            'po_date' => now()->format('Y-m-d'),
            'total' => 0,
            'status' => 'Draft',
            'notes' => 'Penggantian Barang Retur: '.$return->return_number,
        ]);

        foreach ($return->items as $item) {
            PurchaseOrderItem::query()->create([
                'purchase_order_id' => $po->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'unit_price' => 0,
                'subtotal' => 0,
            ]);
        }
    }

    public function claimToSupplier(string $id): ProductReturn
    {
        return DB::transaction(function () use ($id): ProductReturn {
            $customerReturn = ProductReturn::query()->with('items')->findOrFail($id);

            abort_if($customerReturn->type !== 'customer', 422, 'Only customer returns can be claimed to supplier.');
            abort_if($customerReturn->qc_status === 'supplier_claim', 422, 'Return is already claimed to supplier.');

            $poGroups = [];

            foreach ($customerReturn->items as $item) {
                // Find latest PO for this product
                $po = PurchaseOrder::query()
                    ->whereHas('items', function ($query) use ($item) {
                        $query->where('product_id', $item->product_id);
                    })
                    ->latest('created_at')
                    ->first();

                if ($po) {
                    if (! isset($poGroups[$po->id])) {
                        $poGroups[$po->id] = [
                            'supplier_id' => $po->supplier_id,
                            'purchase_order_id' => $po->id,
                            'items' => [],
                        ];
                    }
                    $poGroups[$po->id]['items'][] = clone $item;
                }
            }

            abort_if(empty($poGroups), 422, 'Tidak bisa diklaim. Seluruh barang merupakan produksi internal dan tidak memiliki histori Purchase Order.');

            $customerReturn->qc_status = 'supplier_claim';
            $customerReturn->save();

            foreach ($poGroups as $poId => $group) {
                $supplierReturn = ProductReturn::query()->create([
                    'return_number' => 'RTN-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4)),
                    'type' => 'supplier',
                    'supplier_id' => $group['supplier_id'],
                    'purchase_order_id' => $group['purchase_order_id'],
                    'reason' => 'Otomatis dibuat dari Klaim Pelanggan '.$customerReturn->return_number,
                    'qc_status' => 'pending_qc',
                    'created_by' => auth()->id() ?? $customerReturn->created_by,
                ]);

                foreach ($group['items'] as $item) {
                    ReturnItem::query()->create([
                        'return_id' => $supplierReturn->id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'notes' => $item->notes,
                    ]);
                }
            }

            return $customerReturn;
        });
    }
}
