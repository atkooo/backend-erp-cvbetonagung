<?php

namespace App\Services;

use App\Enums\DeliveryOrderStatus;
use App\Enums\InvoiceStatus;
use App\Enums\SalesOrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SalesWorkflowService
{
    /**
     * Approve quotation dan buat Sales Order baru dari items-nya.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function approveQuotation(string $id, array $attributes): SalesOrder
    {
        return DB::transaction(function () use ($id, $attributes): SalesOrder {
            $quotation = Quotation::query()
                ->with('items')
                ->lockForUpdate()
                ->whereKey($id)
                ->firstOrFail();

            abort_if($quotation->items->isEmpty(), 422, 'Quotation must have at least one item before approval.');
            abort_if($quotation->salesOrders()->exists(), 409, 'Quotation already has a sales order.');

            $quotation->forceFill(['status' => 'approved'])->save();

            $salesOrder = SalesOrder::query()->create([
                'quotation_id' => $quotation->id,
                'order_number' => $attributes['order_number'] ?? null,
                'customer_id' => $quotation->customer_id,
                'order_date' => $attributes['order_date'] ?? date('Y-m-d'),
                'total' => $quotation->total,
                'status' => $attributes['status'] ?? SalesOrderStatus::Processing->value,
                'global_discount_type' => $quotation->global_discount_type,
                'global_discount_value' => $quotation->global_discount_value,
                'global_discount_amount' => $quotation->global_discount_amount,
                'notes' => $attributes['notes'] ?? $quotation->notes,
            ]);

            $this->copyQuotationItemsToSalesOrder($quotation, $salesOrder);

            return $salesOrder;
        });
    }

    /**
     * Approve Sales Order dan otomatis buat Invoice-nya.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function approveSalesOrder(string $id, array $attributes): SalesOrder
    {
        return DB::transaction(function () use ($id, $attributes): SalesOrder {
            $salesOrder = SalesOrder::query()
                ->with('items')
                ->lockForUpdate()
                ->whereKey($id)
                ->firstOrFail();

            abort_if($salesOrder->items->isEmpty(), 422, 'Sales order must have at least one item before approval.');
            abort_if(
                $salesOrder->status !== 'draft' && $salesOrder->status !== 'processing',
                422,
                'Only draft or processing sales orders can be approved.'
            );

            $salesOrder->forceFill(['status' => SalesOrderStatus::Approved->value])->save();

            $invoice = Invoice::query()->create([
                'sales_order_id' => $salesOrder->id,
                'customer_id' => $salesOrder->customer_id,
                'invoice_date' => $attributes['invoice_date'] ?? date('Y-m-d'),
                'due_date' => $attributes['due_date'] ?? date('Y-m-d', strtotime('+30 days')),
                'subtotal' => $salesOrder->total,
                'tax_amount' => 0,
                'total' => $salesOrder->total,
                'status' => InvoiceStatus::Unpaid->value,
            ]);

            foreach ($salesOrder->items as $item) {
                InvoiceItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $item->product_id,
                    'description' => $item->specification ?: $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->subtotal,
                ]);
            }

            return $salesOrder;
        });
    }

    /**
     * Buat Quotation baru beserta item-itemnya dalam satu transaksi.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createQuotation(array $attributes): Quotation
    {
        return DB::transaction(function () use ($attributes): Quotation {
            $items = $attributes['items'] ?? [];
            unset($attributes['items']);

            $quotation = Quotation::query()->create($attributes);

            if (! empty($items)) {
                [$subtotal, $taxAmount] = $this->createLineItems($quotation, 'quotation', $items);
                $quotation->forceFill([
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total' => $subtotal + $taxAmount,
                ])->save();
            }

            return $quotation->fresh(['customer', 'items.product']) ?? $quotation;
        });
    }

    /**
     * Update Quotation dan sinkronisasi item-itemnya.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateQuotation(string $id, array $attributes): Quotation
    {
        return DB::transaction(function () use ($id, $attributes): Quotation {
            $quotation = Quotation::query()->whereKey($id)->firstOrFail();

            $hasItems = array_key_exists('items', $attributes);
            $items = $attributes['items'] ?? null;
            unset($attributes['items']);

            $quotation->fill($attributes)->save();

            if ($hasItems && $items !== null) {
                $quotation->items()->delete();
                $taxAmount = $attributes['tax_amount'] ?? $quotation->tax_amount ?? 0;
                [$subtotal] = $this->createLineItems($quotation, 'quotation', $items, $taxAmount);
                $quotation->forceFill([
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total' => $subtotal + $taxAmount,
                ])->save();
            }

            return $quotation->fresh(['customer', 'items.product']) ?? $quotation;
        });
    }

    /**
     * Buat Sales Order baru beserta item-itemnya dalam satu transaksi.
     * Jika berasal dari quotation, items akan di-copy dari quotation.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createSalesOrder(array $attributes): SalesOrder
    {
        return DB::transaction(function () use ($attributes): SalesOrder {
            $hasItems = array_key_exists('items', $attributes);
            $items = $attributes['items'] ?? [];
            unset($attributes['items']);

            $attributes['source'] = 'erp';
            $salesOrder = SalesOrder::query()->create($attributes);

            // Jika berasal dari quotation dan tidak ada items eksplisit → copy dari quotation
            if (! empty($attributes['quotation_id']) && (! $hasItems || empty($items))) {
                $quotation = Quotation::query()->with('items')->find($attributes['quotation_id']);
                if ($quotation) {
                    $quotation->forceFill(['status' => 'approved'])->save();
                    [$salesOrder] = $this->copyQuotationItemsToSalesOrder($quotation, $salesOrder);
                    $salesOrder->forceFill(['total' => $quotation->total])->save();
                }
            } elseif ($hasItems && ! empty($items)) {
                [$subtotal] = $this->createLineItems($salesOrder, 'sales-order', $items);
                $salesOrder->forceFill(['total' => $subtotal])->save();
            }

            return $salesOrder->fresh(['customer', 'quotation', 'items.product', 'deliveryOrders']) ?? $salesOrder;
        });
    }

    /**
     * Update Sales Order dan sinkronisasi item-itemnya.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateSalesOrder(string $id, array $attributes): SalesOrder
    {
        return DB::transaction(function () use ($id, $attributes): SalesOrder {
            $salesOrder = SalesOrder::query()->whereKey($id)->firstOrFail();

            $hasItems = array_key_exists('items', $attributes);
            $items = $attributes['items'] ?? null;
            unset($attributes['items']);

            $salesOrder->fill($attributes)->save();

            if ($hasItems && $items !== null) {
                $salesOrder->items()->delete();
                [$subtotal] = $this->createLineItems($salesOrder, 'sales-order', $items);
                $salesOrder->forceFill(['total' => $subtotal])->save();
            }

            return $salesOrder->fresh(['customer', 'quotation', 'items.product', 'deliveryOrders']) ?? $salesOrder;
        });
    }

    /**
     * Buat Delivery Order dari Sales Order yang sudah disetujui.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createDeliveryOrder(string $id, array $attributes): DeliveryOrder
    {
        return DB::transaction(function () use ($id, $attributes): DeliveryOrder {
            $salesOrder = SalesOrder::query()
                ->with('items')
                ->lockForUpdate()
                ->whereKey($id)
                ->firstOrFail();

            abort_if($salesOrder->items->isEmpty(), 422, 'Sales order must have at least one item before delivery.');
            abort_if($salesOrder->status !== 'approved', 422, 'Only approved sales orders can be delivered.');
            abort_if($salesOrder->deliveryOrders()->exists(), 409, 'Sales order already has a delivery order.');

            $invoice = $salesOrder->invoices()->first();
            abort_if(! $invoice, 422, 'Sales order must have an invoice before delivery.');
            abort_if((float) $invoice->paid_amount <= 0, 422, 'Invoice must be partially or fully paid before delivery.');

            if ($salesOrder->status === 'draft') {
                $salesOrder->forceFill(['status' => SalesOrderStatus::Processing->value])->save();
            }

            $targetStatus = $attributes['status'] ?? 'ready_to_load';

            if ($targetStatus === DeliveryOrderStatus::ReadyToLoad->value) {
                foreach ($salesOrder->items as $item) {
                    $qtyNeeded = $item->piece_count ?? $item->quantity;
                    $this->checkProductStock($item->product_id, $qtyNeeded);
                }
            }

            $deliveryOrder = DeliveryOrder::query()->create([
                'delivery_number' => $attributes['delivery_number'] ?? null,
                'sales_order_id' => $salesOrder->id,
                'customer_id' => $salesOrder->customer_id,
                'delivery_date' => $attributes['delivery_date'] ?? null,
                'receiver_name' => $attributes['receiver_name'] ?? null,
                'status' => $targetStatus,
                'notes' => $attributes['notes'] ?? $salesOrder->notes,
            ]);

            foreach ($salesOrder->items as $item) {
                DeliveryOrderItem::query()->create([
                    'delivery_order_id' => $deliveryOrder->id,
                    'sales_order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->piece_count ?? $item->quantity,
                ]);
            }

            return $deliveryOrder;
        });
    }

    /**
     * Kirim Delivery Order — kurangi stok dan catat mutasi.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function shipDeliveryOrder(string $id, array $attributes): DeliveryOrder
    {
        return DB::transaction(function () use ($id, $attributes): DeliveryOrder {
            $deliveryOrder = DeliveryOrder::query()
                ->with('items')
                ->lockForUpdate()
                ->whereKey($id)
                ->firstOrFail();

            abort_if($deliveryOrder->items->isEmpty(), 422, 'Delivery order must have at least one item before shipping.');
            abort_if($deliveryOrder->status === 'cancelled', 422, 'Cancelled delivery order cannot be shipped.');
            abort_if($deliveryOrder->status === 'shipped', 409, 'Delivery order has already been shipped.');
            abort_if($deliveryOrder->status === 'received', 409, 'Received delivery order cannot be shipped again.');

            foreach ($deliveryOrder->items as $item) {
                $stock = ProductStock::query()
                    ->where('product_id', $item->product_id)
                    ->where('location_id', $attributes['from_location_id'])
                    ->lockForUpdate()
                    ->first();

                abort_if($stock === null, 422, 'Product stock record does not exist for the selected location.');
                abort_if((float) $stock->quantity < (float) $item->quantity, 422, 'Insufficient stock for delivery shipment.');

                $stock->quantity = (float) $stock->quantity - (float) $item->quantity;
                $stock->save();

                StockMovement::query()->create([
                    'product_id' => $item->product_id,
                    'from_location_id' => $attributes['from_location_id'],
                    'to_location_id' => null,
                    'type' => 'out',
                    'quantity' => $item->quantity,
                    'reference_type' => 'delivery_order',
                    'reference_id' => $deliveryOrder->id,
                    'reference_number' => $deliveryOrder->delivery_number,
                    'handled_by' => $attributes['handled_by'] ?? null,
                    'notes' => $attributes['notes'] ?? null,
                    'movement_at' => $attributes['movement_at'],
                ]);
            }

            $deliveryOrder->forceFill(['status' => DeliveryOrderStatus::Shipped->value])->save();

            return $deliveryOrder;
        });
    }

    /**
     * Helper: buat line items untuk model (Quotation atau SalesOrder).
     * Menghitung subtotal per item dan mengembalikan total subtotal + tax.
     *
     * @param  Model  $parent
     * @param  string  $type  'quotation' | 'sales-order'
     * @param  array<int, array<string, mixed>>  $items
     * @return array{0: float, 1: float} [subtotal, taxAmount]
     */
    private function createLineItems($parent, string $type, array $items, float $taxAmount = 0): array
    {
        $subtotal = 0.0;

        foreach ($items as $itemData) {
            $discountAmount = (float) ($itemData['discount_amount'] ?? 0);
            $itemSubtotal = ((float) ($itemData['quantity'] ?? 0) * (float) ($itemData['unit_price'] ?? 0)) - $discountAmount;
            $subtotal += $itemSubtotal;

            $parent->items()->create([
                'product_id' => $itemData['product_id'],
                'description' => $itemData['description'] ?? null,
                'piece_count' => $itemData['piece_count'] ?? null,
                'length' => $itemData['length'] ?? null,
                'specification' => $itemData['specification'] ?? null,
                'quantity' => $itemData['quantity'],
                'unit_price' => $itemData['unit_price'],
                'discount_amount' => $itemData['discount_amount'] ?? 0,
                'subtotal' => $itemSubtotal,
            ]);
        }

        return [$subtotal, $taxAmount];
    }

    /**
     * Proses transaksi POS (Kasir Cepat).
     * Menerima items, membuat SO, dan membuat Invoice (paid).
     * Jika take_away, memotong stok langsung dan status SO completed.
     * Jika delivery, membuat DeliveryOrder dan status SO pending_delivery.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function processPOS(array $attributes): SalesOrder
    {
        return DB::transaction(function () use ($attributes): SalesOrder {
            $items = $attributes['items'] ?? [];
            abort_if(empty($items), 422, 'POS transaction must have at least one item.');

            $globalDiscountAmount = $attributes['global_discount_amount'] ?? 0;

            $salesOrder = SalesOrder::query()->create([
                'customer_id' => $attributes['customer_id'],
                'order_date' => $attributes['transaction_date'] ?? date('Y-m-d'),
                'total' => 0,
                'status' => SalesOrderStatus::Completed->value,
                'source' => 'pos',
                'notes' => $attributes['notes'] ?? 'Transaksi POS',
                'global_discount_type' => $attributes['global_discount_type'] ?? null,
                'global_discount_value' => $attributes['global_discount_value'] ?? null,
                'global_discount_amount' => $globalDiscountAmount,
            ]);

            [$subtotal] = $this->createLineItems($salesOrder, 'sales-order', $items);
            $finalTotal = max(0, $subtotal - $globalDiscountAmount);
            $salesOrder->forceFill(['total' => $finalTotal])->save();

            [$readyTakeAwayItems, $readyDeliveryItems, $poItems] = $this->classifyPosItems($salesOrder, $items);

            if (! empty($readyDeliveryItems) || ! empty($poItems)) {
                $salesOrder->status = SalesOrderStatus::PendingDelivery->value;
                $salesOrder->save();
            }

            $this->deductTakeAwayStock($readyTakeAwayItems, $salesOrder);
            $this->createPosDeliveryOrders($salesOrder, $readyDeliveryItems, $poItems);

            $invoice = $this->createPosInvoice($salesOrder, $attributes, $subtotal);

            if (($amountPaid = (float) ($attributes['amount_paid'] ?? $subtotal)) > 0) {
                app(FinanceWorkflowService::class)->recordCashTransaction([
                    'account_id' => $attributes['payment_account_id'],
                    'transaction_date' => $salesOrder->order_date,
                    'type' => 'in',
                    'amount' => $amountPaid,
                    'category' => 'sales',
                    'description' => 'Pembayaran POS: '.$salesOrder->order_number,
                    'reference_type' => 'invoice',
                    'reference_id' => $invoice->id,
                    'recorded_by' => auth()->id() ?? $attributes['handled_by'] ?? null,
                ]);
            }

            $salesOrder->load(['customer', 'items.product', 'invoices', 'deliveryOrders.items']);

            return $salesOrder;
        });
    }

    /**
     * Klasifikasikan items POS ke dalam: take away, delivery, atau PO/backorder.
     *
     * @param  array<int, array<string, mixed>>  $rawItems
     * @return array{0: array<int, mixed>, 1: array<int, mixed>, 2: array<int, mixed>}
     */
    private function classifyPosItems(SalesOrder $salesOrder, array $rawItems): array
    {
        $readyTakeAway = [];
        $readyDelivery = [];
        $poItems = [];

        foreach ($salesOrder->items as $index => $item) {
            $product = Product::find($item->product_id);
            $locationId = $rawItems[$index]['location_id'] ?? null;
            $fulfillment = $rawItems[$index]['fulfillment_type'] ?? 'take_away';

            if ($product && $product->is_customizable) {
                $poItems[] = $item;

                continue;
            }

            $available = (float) ProductStock::query()
                ->where('product_id', $item->product_id)
                ->lockForUpdate()
                ->sum('quantity');

            $requested = (float) $item->quantity;

            if ($requested <= $available) {
                $bucket = $fulfillment === 'delivery' ? 'readyDelivery' : 'readyTakeAway';
                $$bucket[] = ['item' => $item, 'location_id' => $locationId, 'quantity' => $requested];
            } else {
                if ($available > 0) {
                    $bucket = $fulfillment === 'delivery' ? 'readyDelivery' : 'readyTakeAway';
                    $$bucket[] = ['item' => $item, 'location_id' => $locationId, 'quantity' => $available];
                }
                $backorderItem = clone $item;
                $backorderItem->quantity = $requested - $available;
                $poItems[] = $backorderItem;
            }
        }

        return [$readyTakeAway, $readyDelivery, $poItems];
    }

    /**
     * Kurangi stok untuk item take away (POS instan).
     *
     * @param  array<int, mixed>  $takeAwayItems
     */
    private function deductTakeAwayStock(array $takeAwayItems, SalesOrder $salesOrder): void
    {
        foreach ($takeAwayItems as $rItem) {
            $item = $rItem['item'];
            $qtyToCut = $rItem['quantity'];
            $remaining = $qtyToCut;

            $stocks = ProductStock::query()
                ->where('product_id', $item->product_id)
                ->where('quantity', '>', 0)
                ->lockForUpdate()
                ->get();

            foreach ($stocks as $stock) {
                if ($remaining <= 0) {
                    break;
                }

                $cut = min((float) $stock->quantity, $remaining);
                $stock->quantity = (float) $stock->quantity - $cut;
                $stock->save();

                StockMovement::query()->create([
                    'product_id' => $item->product_id,
                    'from_location_id' => $stock->location_id,
                    'to_location_id' => null,
                    'type' => 'out',
                    'quantity' => $cut,
                    'reference_type' => 'pos',
                    'reference_id' => $salesOrder->id,
                    'reference_number' => $salesOrder->order_number,
                    'handled_by' => auth()->id(),
                    'notes' => 'Transaksi POS (Take Away)',
                    'movement_at' => now(),
                ]);

                $remaining -= $cut;
            }
        }
    }

    /**
     * Buat Delivery Orders untuk items yang perlu dikirim / backordered.
     *
     * @param  array<int, mixed>  $readyDelivery
     * @param  array<int, mixed>  $poItems
     */
    private function createPosDeliveryOrders(SalesOrder $salesOrder, array $readyDelivery, array $poItems): void
    {
        if (! empty($readyDelivery)) {
            $do = DeliveryOrder::query()->create([
                'delivery_number' => 'DO-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4)),
                'sales_order_id' => $salesOrder->id,
                'customer_id' => $salesOrder->customer_id,
                'status' => DeliveryOrderStatus::ReadyToLoad->value,
                'notes' => 'Otomatis dari POS (Barang Ready - Kirim ke Lokasi)',
            ]);
            foreach ($readyDelivery as $rItem) {
                $do->items()->create([
                    'sales_order_item_id' => $rItem['item']->id,
                    'product_id' => $rItem['item']->product_id,
                    'quantity' => $rItem['quantity'],
                ]);
            }
        }

        if (! empty($poItems)) {
            $do = DeliveryOrder::query()->create([
                'delivery_number' => 'DO-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4)),
                'sales_order_id' => $salesOrder->id,
                'customer_id' => $salesOrder->customer_id,
                'status' => DeliveryOrderStatus::Draft->value,
                'notes' => 'Otomatis dari POS (Barang PO/Inden - Kirim Menyusul)',
            ]);
            foreach ($poItems as $pItem) {
                $do->items()->create([
                    'sales_order_item_id' => $pItem->id,
                    'product_id' => $pItem->product_id,
                    'quantity' => $pItem->quantity,
                ]);
            }
        }
    }

    /**
     * Buat Invoice untuk transaksi POS.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createPosInvoice(SalesOrder $salesOrder, array $attributes, float $subtotal): Invoice
    {
        $amountPaid = isset($attributes['amount_paid']) ? (float) $attributes['amount_paid'] : $subtotal;
        $invoiceStatus = $amountPaid >= $subtotal ? InvoiceStatus::Paid->value : InvoiceStatus::Partial->value;

        $invoice = Invoice::query()->create([
            'sales_order_id' => $salesOrder->id,
            'customer_id' => $salesOrder->customer_id,
            'invoice_date' => $salesOrder->order_date,
            'due_date' => $salesOrder->order_date,
            'subtotal' => $subtotal,
            'tax_amount' => 0,
            'total' => $subtotal,
            'status' => $invoiceStatus,
            'paid_amount' => min($amountPaid, $subtotal),
        ]);

        foreach ($salesOrder->items as $item) {
            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'product_id' => $item->product_id,
                'description' => $item->specification ?: $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
            ]);
        }

        return $invoice;
    }

    /**
     * Copy items dari Quotation ke SalesOrder.
     * Digunakan oleh approveQuotation() dan createSalesOrder().
     *
     * @return array{0: SalesOrder}
     */
    private function copyQuotationItemsToSalesOrder(Quotation $quotation, SalesOrder $salesOrder): array
    {
        foreach ($quotation->items as $item) {
            SalesOrderItem::query()->create([
                'sales_order_id' => $salesOrder->id,
                'product_id' => $item->product_id,
                'description' => $item->description,
                'piece_count' => $item->piece_count,
                'length' => $item->length,
                'specification' => $item->specification,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount_amount' => $item->discount_amount ?? 0,
                'subtotal' => $item->subtotal,
            ]);
        }

        return [$salesOrder];
    }

    /**
     * Update Delivery Order: cek stok jika status berubah ke ready_to_load,
     * dan otomatis complete SO jika status berubah ke received.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateDeliveryOrder(string $id, array $attributes): DeliveryOrder
    {
        return DB::transaction(function () use ($id, $attributes): DeliveryOrder {
            $deliveryOrder = DeliveryOrder::query()
                ->with('items.product')
                ->whereKey($id)
                ->firstOrFail();

            if (
                ($attributes['status'] ?? '') === DeliveryOrderStatus::ReadyToLoad->value
                && $deliveryOrder->status !== DeliveryOrderStatus::ReadyToLoad->value
            ) {
                foreach ($deliveryOrder->items as $item) {
                    $this->checkProductStock($item->product_id, $item->quantity);
                }
            }

            $deliveryOrder->update($attributes);

            if (($attributes['status'] ?? '') === DeliveryOrderStatus::Received->value && $deliveryOrder->sales_order_id) {
                SalesOrder::query()
                    ->whereKey($deliveryOrder->sales_order_id)
                    ->first()
                    ?->forceFill(['status' => SalesOrderStatus::Completed->value])
                    ->save();
            }

            return $deliveryOrder;
        });
    }

    /**
     * Check product stock and throw exception if insufficient.
     *
     * @throws InsufficientStockException
     */
    public function checkProductStock(string $productId, float $qtyNeeded): void
    {
        $totalStock = ProductStock::where('product_id', $productId)->lockForUpdate()->sum('quantity');

        if ((float) $totalStock < (float) $qtyNeeded) {
            $productName = Product::find($productId)->name ?? 'Unknown';
            throw new InsufficientStockException($productName, $qtyNeeded, $totalStock);
        }
    }
}
