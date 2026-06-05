<?php

namespace Tests\Feature\Api;

use App\Models\ApprovalRequest;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\StockOpnameSession;
use App\Models\StorageLocation;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_stock_can_be_listed_created_shown_updated_and_deleted(): void
    {
        $this->seed();

        $product = Product::query()->where('sku', 'PRC-0001')->firstOrFail();
        $location = StorageLocation::query()->where('code', 'DEFAULT')->firstOrFail();

        $this->postJson('/api/inventory/product-stocks', [
            'product_id' => $product->id,
            'location_id' => $location->id,
            'quantity' => 10,
        ])
            ->assertCreated()
            ->assertJsonPath('data.quantity', '10.00')
            ->assertJsonPath('data.product.sku', 'PRC-0001');

        $this->getJson('/api/inventory/product-stocks?product_id='.$product->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $stockUrl = "/api/inventory/product-stocks/{$product->id}/{$location->id}";

        $this->getJson($stockUrl)
            ->assertOk()
            ->assertJsonPath('data.location.code', 'DEFAULT');

        $this->patchJson($stockUrl, ['quantity' => 12.5])
            ->assertOk()
            ->assertJsonPath('data.quantity', '12.50');

        $this->deleteJson($stockUrl)
            ->assertNoContent();

        $this->assertDatabaseMissing('product_stocks', [
            'product_id' => $product->id,
            'location_id' => $location->id,
        ]);
    }

    public function test_stock_movement_api_returns_inventory_relationships(): void
    {
        $this->seed();

        $product = Product::query()->where('sku', 'PRC-0001')->firstOrFail();
        $location = StorageLocation::query()->where('code', 'DEFAULT')->firstOrFail();
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $response = $this->postJson('/api/inventory/stock-movements', [
            'product_id' => $product->id,
            'to_location_id' => $location->id,
            'type' => 'in',
            'quantity' => 3,
            'reference_type' => 'manual',
            'reference_number' => 'API-MOVE-001',
            'handled_by' => $admin->id,
            'movement_at' => '2026-06-05 08:00:00',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.reference_number', 'API-MOVE-001')
            ->assertJsonPath('data.product.sku', 'PRC-0001')
            ->assertJsonPath('data.to_location.code', 'DEFAULT');

        $id = $response->json('data.id');

        $this->getJson('/api/inventory/stock-movements?q=API-MOVE')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->patchJson("/api/inventory/stock-movements/{$id}", [
            'notes' => 'Updated via API.',
        ])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Updated via API.');
    }

    public function test_stock_opname_session_and_item_can_be_created(): void
    {
        $this->seed();

        $warehouse = Warehouse::query()->where('code', 'GDG-UTM')->firstOrFail();
        $product = Product::query()->where('sku', 'PRC-0001')->firstOrFail();
        $location = StorageLocation::query()->where('code', 'DEFAULT')->firstOrFail();

        $sessionResponse = $this->postJson('/api/inventory/stock-opname-sessions', [
            'opname_number' => 'OPN-API-001',
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'started_at' => '2026-06-05 09:00:00',
        ]);

        $sessionResponse
            ->assertCreated()
            ->assertJsonPath('data.opname_number', 'OPN-API-001')
            ->assertJsonPath('data.warehouse.code', 'GDG-UTM');

        $sessionId = $sessionResponse->json('data.id');

        $this->postJson('/api/inventory/stock-opname-items', [
            'session_id' => $sessionId,
            'product_id' => $product->id,
            'location_id' => $location->id,
            'system_qty' => 0,
            'physical_qty' => 2,
            'difference_qty' => 2,
            'notes' => 'API opname item.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.session.opname_number', 'OPN-API-001')
            ->assertJsonPath('data.product.sku', 'PRC-0001');
    }

    public function test_approval_request_can_be_created_for_stock_opname_reference(): void
    {
        $this->seed();

        $session = StockOpnameSession::query()->where('opname_number', 'OPN-INIT')->firstOrFail();
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->postJson('/api/inventory/approval-requests', [
            'approval_number' => 'APR-API-001',
            'request_type' => 'stock_opname_adjustment',
            'requester_id' => $admin->id,
            'approver_id' => $admin->id,
            'reference_type' => 'OPNAME',
            'reference_id' => $session->id,
            'reference_number' => $session->opname_number,
            'status' => 'pending',
            'requested_at' => '2026-06-05 10:00:00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.approval_number', 'APR-API-001')
            ->assertJsonPath('data.requester.email', 'admin@example.com');
    }

    public function test_approved_stock_opname_item_can_adjust_stock_and_create_movement(): void
    {
        $this->seed();

        $warehouse = Warehouse::query()->where('code', 'GDG-UTM')->firstOrFail();
        $product = Product::query()->where('sku', 'PRC-0001')->firstOrFail();
        $location = StorageLocation::query()->where('code', 'DEFAULT')->firstOrFail();
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        ProductStock::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'location_id' => $location->id,
            ],
            ['quantity' => 5],
        );

        $sessionId = $this->postJson('/api/inventory/stock-opname-sessions', [
            'opname_number' => 'OPN-API-ADJUST',
            'warehouse_id' => $warehouse->id,
            'started_by' => $admin->id,
            'status' => 'in_progress',
            'started_at' => '2026-06-05 09:00:00',
        ])->assertCreated()->json('data.id');

        $approval = ApprovalRequest::query()->create([
            'approval_number' => 'APR-API-ADJUST',
            'request_type' => 'stock_opname_adjustment',
            'requester_id' => $admin->id,
            'approver_id' => $admin->id,
            'reference_type' => 'OPNAME',
            'reference_id' => $sessionId,
            'reference_number' => 'OPN-API-ADJUST',
            'status' => 'approved',
            'requested_at' => '2026-06-05 10:00:00',
            'decided_at' => '2026-06-05 11:00:00',
        ]);

        $itemId = $this->postJson('/api/inventory/stock-opname-items', [
            'session_id' => $sessionId,
            'product_id' => $product->id,
            'location_id' => $location->id,
            'system_qty' => 5,
            'physical_qty' => 7,
            'difference_qty' => 2,
            'approval_request_id' => $approval->id,
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/inventory/stock-opname-items/{$itemId}/adjust", [
            'handled_by' => $admin->id,
            'movement_at' => '2026-06-05 12:00:00',
            'notes' => 'Adjusted via API workflow.',
        ])
            ->assertOk()
            ->assertJsonPath('data.session.status', 'closed')
            ->assertJsonPath('data.approval_request.status', 'approved');

        $stock = ProductStock::query()
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->firstOrFail();

        $this->assertSame('7.00', $stock->quantity);

        $movement = StockMovement::query()
            ->where('reference_type', 'stock_opname_item')
            ->where('reference_id', $itemId)
            ->firstOrFail();

        $this->assertSame('adjustment', $movement->type);
        $this->assertSame('2.00', $movement->quantity);
        $this->assertSame($location->id, $movement->to_location_id);
    }
}
