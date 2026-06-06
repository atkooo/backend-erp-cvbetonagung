<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\Rfq;
use App\Models\PurchaseOrder;
use App\Models\GoodsReceiptNote;
use Illuminate\Http\Request;

class ProcurementController extends Controller
{
    // --- Purchase Requests ---
    public function getPurchaseRequests()
    {
        $data = PurchaseRequest::with(['requester', 'items.product'])->orderBy('created_at', 'desc')->get();
        return response()->json(['data' => $data]);
    }

    public function storePurchaseRequest(Request $request)
    {
        // Basic implementation
        $pr = PurchaseRequest::create([
            'pr_number' => 'PR-' . date('Ymd') . '-' . rand(1000, 9999),
            'requester_id' => $request->user()->id,
            'request_date' => $request->request_date ?? now(),
            'required_date' => $request->required_date,
            'department' => $request->department,
            'notes' => $request->notes,
        ]);

        if ($request->has('items')) {
            foreach ($request->items as $item) {
                $pr->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'description' => $item['description'] ?? '',
                    'quantity' => $item['quantity'],
                ]);
            }
        }

        return response()->json(['data' => $pr->load('items')], 201);
    }

    // --- RFQs ---
    public function getRfqs()
    {
        $data = Rfq::with(['supplier', 'purchaseRequest', 'items.product'])->orderBy('created_at', 'desc')->get();
        return response()->json(['data' => $data]);
    }

    public function storeRfq(Request $request)
    {
        $rfq = Rfq::create([
            'rfq_number' => 'RFQ-' . date('Ymd') . '-' . rand(1000, 9999),
            'purchase_request_id' => $request->purchase_request_id,
            'supplier_id' => $request->supplier_id,
            'rfq_date' => $request->rfq_date ?? now(),
            'valid_until' => $request->valid_until,
            'notes' => $request->notes,
        ]);

        if ($request->has('items')) {
            foreach ($request->items as $item) {
                $rfq->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'description' => $item['description'] ?? '',
                    'quantity' => $item['quantity'],
                    'quoted_unit_price' => $item['quoted_unit_price'] ?? 0,
                    'subtotal' => ($item['quantity'] * ($item['quoted_unit_price'] ?? 0)),
                ]);
            }
        }

        return response()->json(['data' => $rfq->load('items')], 201);
    }

    // --- Purchase Orders ---
    public function getPurchaseOrders()
    {
        $data = PurchaseOrder::with(['supplier', 'purchaseRequest', 'rfq', 'items.product'])->orderBy('created_at', 'desc')->get();
        return response()->json(['data' => $data]);
    }

    public function storePurchaseOrder(Request $request)
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-' . date('Ymd') . '-' . rand(1000, 9999),
            'supplier_id' => $request->supplier_id,
            'purchase_request_id' => $request->purchase_request_id,
            'rfq_id' => $request->rfq_id,
            'po_date' => $request->po_date ?? now(),
            'total' => $request->total ?? 0,
            'notes' => $request->notes,
        ]);

        if ($request->has('items')) {
            foreach ($request->items as $item) {
                $po->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'description' => $item['description'] ?? '',
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'] ?? 0,
                    'subtotal' => ($item['quantity'] * ($item['unit_price'] ?? 0)),
                ]);
            }
        }

        return response()->json(['data' => $po->load('items')], 201);
    }

    // --- Goods Receipt Notes ---
    public function getGoodsReceiptNotes()
    {
        $data = GoodsReceiptNote::with(['purchaseOrder', 'warehouse', 'receiver', 'items.product'])->orderBy('created_at', 'desc')->get();
        return response()->json(['data' => $data]);
    }

    public function storeGoodsReceiptNote(Request $request)
    {
        $grn = GoodsReceiptNote::create([
            'grn_number' => 'GRN-' . date('Ymd') . '-' . rand(1000, 9999),
            'purchase_order_id' => $request->purchase_order_id,
            'warehouse_id' => $request->warehouse_id,
            'received_by' => $request->user()->id,
            'receipt_date' => $request->receipt_date ?? now(),
            'delivery_order_number' => $request->delivery_order_number,
            'notes' => $request->notes,
        ]);

        if ($request->has('items')) {
            foreach ($request->items as $item) {
                $grn->items()->create([
                    'purchase_order_item_id' => $item['purchase_order_item_id'] ?? null,
                    'product_id' => $item['product_id'] ?? null,
                    'received_qty' => $item['received_qty'] ?? 0,
                    'rejected_qty' => $item['rejected_qty'] ?? 0,
                    'notes' => $item['notes'] ?? '',
                ]);
            }
        }

        return response()->json(['data' => $grn->load('items')], 201);
    }
}
