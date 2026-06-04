<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\StockOpnameItem;
use App\Models\StockOpnameSession;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_seed_creates_stock_and_initial_session(): void
    {
        $this->seed();

        $this->assertDatabaseHas('product_stocks', ['quantity' => 0]);
        $this->assertDatabaseHas('stock_movements', ['reference_number' => 'INIT-STOCK']);
        $this->assertDatabaseHas('stock_opname_sessions', ['opname_number' => 'OPN-INIT']);
    }

    public function test_product_stock_relations_are_available(): void
    {
        $this->seed();

        $stock = ProductStock::query()->firstOrFail();

        $this->assertInstanceOf(Product::class, $stock->product);
        $this->assertInstanceOf(StorageLocation::class, $stock->location);
        $this->assertSame('0.00', $stock->quantity);
    }

    public function test_stock_movement_relations_are_available(): void
    {
        $this->seed();

        $movement = StockMovement::query()
            ->where('reference_number', 'INIT-STOCK')
            ->firstOrFail();

        $this->assertSame('PRC-0001', $movement->product->sku);
        $this->assertSame('DEFAULT', $movement->toLocation?->code);
        $this->assertSame('admin@example.com', $movement->handledBy?->email);
    }

    public function test_stock_opname_item_can_reference_approval_request(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $session = StockOpnameSession::query()->where('opname_number', 'OPN-INIT')->firstOrFail();
        $stock = ProductStock::query()->firstOrFail();

        $approval = ApprovalRequest::query()->create([
            'approval_number' => 'APR-OPN-001',
            'request_type' => 'stock_opname_adjustment',
            'requester_id' => $admin->id,
            'approver_id' => $admin->id,
            'reference_type' => 'OPNAME',
            'reference_id' => $session->id,
            'reference_number' => $session->opname_number,
            'change_summary' => 'Adjust stock opname difference.',
            'amount' => 0,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $item = StockOpnameItem::query()->create([
            'session_id' => $session->id,
            'product_id' => $stock->product_id,
            'location_id' => $stock->location_id,
            'system_qty' => 0,
            'physical_qty' => 1,
            'difference_qty' => 1,
            'notes' => 'Test adjustment.',
            'approval_request_id' => $approval->id,
        ]);

        $this->assertSame('APR-OPN-001', $item->approvalRequest?->approval_number);
        $this->assertSame('OPN-INIT', $item->session->opname_number);
        $this->assertSame('pending', $approval->status);
    }
}
