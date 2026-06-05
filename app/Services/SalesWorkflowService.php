<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\ProductStock;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class SalesWorkflowService
{
    /**
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
                'order_number' => $attributes['order_number'],
                'customer_id' => $quotation->customer_id,
                'order_date' => $attributes['order_date'],
                'total' => $quotation->total,
                'status' => $attributes['status'] ?? 'processing',
                'notes' => $attributes['notes'] ?? $quotation->notes,
            ]);

            foreach ($quotation->items as $item) {
                SalesOrderItem::query()->create([
                    'sales_order_id' => $salesOrder->id,
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->subtotal,
                ]);
            }

            return $salesOrder;
        });
    }

    /**
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
            abort_if($salesOrder->deliveryOrders()->exists(), 409, 'Sales order already has a delivery order.');
            abort_if($salesOrder->status === 'cancelled', 422, 'Cancelled sales order cannot be delivered.');

            $deliveryOrder = DeliveryOrder::query()->create([
                'delivery_number' => $attributes['delivery_number'],
                'sales_order_id' => $salesOrder->id,
                'customer_id' => $salesOrder->customer_id,
                'delivery_date' => $attributes['delivery_date'] ?? null,
                'receiver_name' => $attributes['receiver_name'] ?? null,
                'status' => $attributes['status'] ?? 'ready_to_load',
                'notes' => $attributes['notes'] ?? $salesOrder->notes,
            ]);

            foreach ($salesOrder->items as $item) {
                DeliveryOrderItem::query()->create([
                    'delivery_order_id' => $deliveryOrder->id,
                    'sales_order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                ]);
            }

            return $deliveryOrder;
        });
    }

    /**
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
}
