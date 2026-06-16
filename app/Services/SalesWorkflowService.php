<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\ProductStock;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\StockMovement;
use App\Models\CashTransaction;
use Illuminate\Support\Facades\DB;

class SalesWorkflowService
{
    /**
     * Approve quotation dan buat Sales Order baru dari items-nya.
     *
     * @param array<string, mixed> $attributes
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
                'status' => $attributes['status'] ?? 'processing',
                'notes' => $attributes['notes'] ?? $quotation->notes,
            ]);

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
                    'subtotal' => $item->subtotal,
                ]);
            }

            return $salesOrder;
        });
    }

    /**
     * Approve Sales Order dan otomatis buat Invoice-nya.
     *
     * @param array<string, mixed> $attributes
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

            $salesOrder->forceFill(['status' => 'approved'])->save();

            $invoice = Invoice::query()->create([
                'sales_order_id' => $salesOrder->id,
                'customer_id' => $salesOrder->customer_id,
                'invoice_date' => $attributes['invoice_date'] ?? date('Y-m-d'),
                'due_date' => $attributes['due_date'] ?? date('Y-m-d', strtotime('+30 days')),
                'subtotal' => $salesOrder->total,
                'tax_amount' => 0,
                'total' => $salesOrder->total,
                'status' => 'unpaid',
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
     * @param array<string, mixed> $attributes
     */
    public function createQuotation(array $attributes): Quotation
    {
        return DB::transaction(function () use ($attributes): Quotation {
            $items = $attributes['items'] ?? [];
            unset($attributes['items']);

            $quotation = Quotation::query()->create($attributes);

            if (!empty($items)) {
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
     * @param array<string, mixed> $attributes
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
     * @param array<string, mixed> $attributes
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
            if (!empty($attributes['quotation_id']) && (!$hasItems || empty($items))) {
                $quotation = Quotation::query()->with('items')->find($attributes['quotation_id']);
                if ($quotation) {
                    $quotation->forceFill(['status' => 'approved'])->save();
                    $subtotal = 0;
                    foreach ($quotation->items as $qItem) {
                        $salesOrder->items()->create([
                            'product_id' => $qItem->product_id,
                            'description' => $qItem->description,
                            'piece_count' => $qItem->piece_count,
                            'length' => $qItem->length,
                            'specification' => $qItem->specification,
                            'quantity' => $qItem->quantity,
                            'unit_price' => $qItem->unit_price,
                            'subtotal' => $qItem->subtotal,
                        ]);
                        $subtotal += $qItem->subtotal;
                    }
                    $salesOrder->forceFill(['total' => $subtotal])->save();
                }
            } elseif ($hasItems && !empty($items)) {
                [$subtotal] = $this->createLineItems($salesOrder, 'sales-order', $items);
                $salesOrder->forceFill(['total' => $subtotal])->save();
            }

            return $salesOrder->fresh(['customer', 'quotation', 'items.product', 'deliveryOrders']) ?? $salesOrder;
        });
    }

    /**
     * Update Sales Order dan sinkronisasi item-itemnya.
     *
     * @param array<string, mixed> $attributes
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
     * @param array<string, mixed> $attributes
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
            abort_if(!$invoice, 422, 'Sales order must have an invoice before delivery.');
            abort_if((float) $invoice->paid_amount <= 0, 422, 'Invoice must be partially or fully paid before delivery.');

            if ($salesOrder->status === 'draft') {
                $salesOrder->forceFill(['status' => 'processing'])->save();
            }

            $targetStatus = $attributes['status'] ?? 'ready_to_load';

            if ($targetStatus === 'ready_to_load') {
                foreach ($salesOrder->items as $item) {
                    $qtyNeeded = $item->piece_count ?? $item->quantity;
                    $totalStock = \App\Models\ProductStock::where('product_id', $item->product_id)->sum('quantity');
                    if ($totalStock < $qtyNeeded) {
                        $productName = \App\Models\Product::find($item->product_id)->name ?? 'Unknown';
                        abort(422, "Stok tidak mencukupi untuk produk {$productName}. Dibutuhkan: {$qtyNeeded}, Tersedia: {$totalStock}. Silakan buat Work Order/Purchase Order terlebih dahulu.");
                    }
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
     * @param array<string, mixed> $attributes
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

            $deliveryOrder->forceFill(['status' => 'shipped'])->save();

            return $deliveryOrder;
        });
    }

    /**
     * Helper: buat line items untuk model (Quotation atau SalesOrder).
     * Menghitung subtotal per item dan mengembalikan total subtotal + tax.
     *
     * @param \Illuminate\Database\Eloquent\Model $parent
     * @param string $type 'quotation' | 'sales-order'
     * @param array<int, array<string, mixed>> $items
     * @param float $taxAmount
     * @return array{0: float, 1: float} [subtotal, taxAmount]
     */
    private function createLineItems($parent, string $type, array $items, float $taxAmount = 0): array
    {
        $subtotal = 0.0;

        foreach ($items as $itemData) {
            $itemSubtotal = (float) ($itemData['quantity'] ?? 0) * (float) ($itemData['unit_price'] ?? 0);
            $subtotal += $itemSubtotal;

            $parent->items()->create([
                'product_id' => $itemData['product_id'],
                'description' => $itemData['description'] ?? null,
                'piece_count' => $itemData['piece_count'] ?? null,
                'length' => $itemData['length'] ?? null,
                'specification' => $itemData['specification'] ?? null,
                'quantity' => $itemData['quantity'],
                'unit_price' => $itemData['unit_price'],
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
     * @param array<string, mixed> $attributes
     */
    public function processPOS(array $attributes): SalesOrder
    {
        return DB::transaction(function () use ($attributes): SalesOrder {
            $items = $attributes['items'] ?? [];
            abort_if(empty($items), 422, 'POS transaction must have at least one item.');

            $customerId = $attributes['customer_id'];

            // 1. Initial Sales Order
            // Note: We will calculate status dynamically based on items below
            $salesOrder = SalesOrder::query()->create([
                'customer_id' => $customerId,
                'order_date' => $attributes['transaction_date'] ?? date('Y-m-d'),
                'total' => 0,
                'status' => 'completed', // Will update if there are deliveries/POs
                'source' => 'pos',
                'notes' => $attributes['notes'] ?? 'Transaksi POS',
            ]);

            [$subtotal] = $this->createLineItems($salesOrder, 'sales-order', $items);
            $salesOrder->forceFill(['total' => $subtotal])->save();

            // 2. Fulfillment Logic
            $readyTakeAwayItems = [];
            $readyDeliveryItems = [];
            $poItems = [];

            foreach ($salesOrder->items as $index => $item) {
                $product = \App\Models\Product::find($item->product_id);
                $locationId = $items[$index]['location_id'] ?? null;
                $itemFulfillment = $items[$index]['fulfillment_type'] ?? 'take_away';

                if ($product && $product->is_customizable) {
                    $poItems[] = $item;
                } else {
                    $availableStock = (float) ProductStock::query()
                        ->where('product_id', $item->product_id)
                        ->sum('quantity');

                    $requestedQuantity = (float) $item->quantity;

                    if ($requestedQuantity <= $availableStock) {
                        if ($itemFulfillment === 'delivery') {
                            $readyDeliveryItems[] = ['item' => $item, 'location_id' => $locationId, 'quantity' => $requestedQuantity];
                        } else {
                            $readyTakeAwayItems[] = ['item' => $item, 'location_id' => $locationId, 'quantity' => $requestedQuantity];
                        }
                    } else {
                        if ($availableStock > 0) {
                            if ($itemFulfillment === 'delivery') {
                                $readyDeliveryItems[] = ['item' => $item, 'location_id' => $locationId, 'quantity' => $availableStock];
                            } else {
                                $readyTakeAwayItems[] = ['item' => $item, 'location_id' => $locationId, 'quantity' => $availableStock];
                            }
                        }
                        // Create a clone or pseudo-item for PO with remaining quantity
                        $poItem = clone $item;
                        $poItem->quantity = $requestedQuantity - $availableStock;
                        $poItems[] = $poItem;
                    }
                }
            }

            // Update SO Status if any items are NOT taken away instantly
            if (!empty($readyDeliveryItems) || !empty($poItems)) {
                $salesOrder->status = 'pending_delivery';
                $salesOrder->save();
            }

            // 2A. Instant Stock Deductions for Take Away Items
                foreach ($readyTakeAwayItems as $rItem) {
                    $item = $rItem['item'];
                    $locationId = $rItem['location_id'];
                    $qtyToCut = $rItem['quantity'];
                    
                    $stocks = ProductStock::query()
                        ->where('product_id', $item->product_id)
                        ->where('quantity', '>', 0)
                        ->lockForUpdate()
                        ->get();

                    $remainingToCut = $qtyToCut;

                    foreach ($stocks as $stock) {
                        if ($remainingToCut <= 0) break;

                        $cut = min((float) $stock->quantity, $remainingToCut);
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
                            'handled_by' => auth()->id() ?? $attributes['handled_by'] ?? null,
                            'notes' => 'Transaksi POS (Take Away)',
                            'movement_at' => now(),
                        ]);

                        $remainingToCut -= $cut;
                    }
                }

                // 2B. Delivery Orders for Delivered Ready Items
                if (!empty($readyDeliveryItems)) {
                    $deliveryOrderReady = DeliveryOrder::query()->create([
                        'delivery_number' => 'DO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)),
                        'sales_order_id' => $salesOrder->id,
                        'customer_id' => $salesOrder->customer_id,
                        'status' => 'ready_to_load',
                        'notes' => 'Otomatis dari POS (Barang Ready - Kirim ke Lokasi)',
                    ]);
                    foreach ($readyDeliveryItems as $rItem) {
                        $deliveryOrderReady->items()->create([
                            'sales_order_item_id' => $rItem['item']->id,
                            'product_id' => $rItem['item']->product_id,
                            'quantity' => $rItem['quantity'],
                        ]);
                    }
                }

                // 2C. Delivery Orders for PO/Backorder Items
                if (!empty($poItems)) {
                    $deliveryOrderPO = DeliveryOrder::query()->create([
                        'delivery_number' => 'DO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)),
                        'sales_order_id' => $salesOrder->id,
                        'customer_id' => $salesOrder->customer_id,
                        'status' => 'draft',
                        'notes' => 'Otomatis dari POS (Barang PO/Inden - Kirim Menyusul)',
                    ]);
                    foreach ($poItems as $pItem) {
                        $deliveryOrderPO->items()->create([
                            'sales_order_item_id' => $pItem->id,
                            'product_id' => $pItem->product_id,
                            'quantity' => $pItem->quantity,
                        ]);
                    }
                }

            $amountPaid = isset($attributes['amount_paid']) ? (float) $attributes['amount_paid'] : (float) $subtotal;
            $invoiceStatus = $amountPaid >= (float) $subtotal ? 'paid' : 'partial';

            // 3. Create Invoice
            $invoice = Invoice::query()->create([
                'sales_order_id' => $salesOrder->id,
                'customer_id' => $customerId,
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

            // 4. Create Cash Transaction (Receipt)
            if ($amountPaid > 0) {
                app(\App\Services\FinanceWorkflowService::class)->recordCashTransaction([
                    'account_id' => $attributes['payment_account_id'],
                    'transaction_date' => $salesOrder->order_date,
                    'type' => 'in',
                    'amount' => $amountPaid,
                    'category' => 'sales',
                    'description' => 'Pembayaran POS: ' . $salesOrder->order_number,
                    'reference_type' => 'invoice',
                    'reference_id' => $invoice->id,
                    'recorded_by' => auth()->id() ?? $attributes['handled_by'] ?? null,
                ]);
            }

            $salesOrder->load(['customer', 'items.product', 'invoices', 'deliveryOrders.items']);
            return $salesOrder;
        });
    }
}
