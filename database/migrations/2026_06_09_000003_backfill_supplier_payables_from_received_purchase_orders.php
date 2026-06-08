<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $purchaseOrders = DB::table('purchase_orders')
            ->join('purchase_order_items', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
            ->leftJoin('supplier_payables', 'supplier_payables.purchase_order_id', '=', 'purchase_orders.id')
            ->whereNull('supplier_payables.id')
            ->groupBy('purchase_orders.id', 'purchase_orders.po_number', 'purchase_orders.supplier_id')
            ->select([
                'purchase_orders.id',
                'purchase_orders.po_number',
                'purchase_orders.supplier_id',
                DB::raw('SUM(purchase_order_items.received_qty * purchase_order_items.unit_price) as payable_amount'),
            ])
            ->havingRaw('SUM(purchase_order_items.received_qty * purchase_order_items.unit_price) > 0')
            ->get();

        foreach ($purchaseOrders as $purchaseOrder) {
            $payableNumber = 'AP-' . $purchaseOrder->po_number;
            if (DB::table('supplier_payables')->where('payable_number', $payableNumber)->exists()) {
                $payableNumber .= '-' . Str::upper(Str::random(4));
            }

            DB::table('supplier_payables')->insert([
                'id' => (string) Str::uuid(),
                'purchase_order_id' => $purchaseOrder->id,
                'supplier_id' => $purchaseOrder->supplier_id,
                'payable_number' => $payableNumber,
                'due_date' => $now->copy()->addDays(30)->toDateString(),
                'amount' => $purchaseOrder->payable_amount,
                'paid_amount' => 0,
                'status' => 'open',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Keep AP data intact on rollback.
    }
};
