<?php

namespace Tests\Feature\Api;

use App\Models\ProductCategory;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_master_data_can_be_created_listed_updated_and_deleted(): void
    {
        $createResponse = $this->postJson('/api/master-data/customers', [
            'code' => 'CUST-API-001',
            'name' => 'PT Api Beton',
            'phone' => '08123456789',
            'email' => 'buyer@example.com',
            'city' => 'Jakarta',
            'address' => 'Jl. Testing No. 1',
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.code', 'CUST-API-001')
            ->assertJsonPath('data.status', 'active');

        $id = $createResponse->json('data.id');

        $this->getJson('/api/master-data/customers?q=Api')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $id);

        $this->patchJson("/api/master-data/customers/{$id}", [
            'name' => 'PT Api Beton Updated',
            'status' => 'inactive',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'PT Api Beton Updated')
            ->assertJsonPath('data.status', 'inactive');

        $this->deleteJson("/api/master-data/customers/{$id}")
            ->assertNoContent();

        $this->assertSoftDeleted('customers', ['id' => $id]);
    }

    public function test_master_data_unique_validation_ignores_current_record_on_update(): void
    {
        $response = $this->postJson('/api/master-data/units', [
            'code' => 'API',
            'name' => 'Api Unit',
        ])->assertCreated();

        $id = $response->json('data.id');

        $this->patchJson("/api/master-data/units/{$id}", [
            'code' => 'API',
            'name' => 'Api Unit Updated',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Api Unit Updated');
    }

    public function test_product_master_data_validates_relationships_and_returns_loaded_relations(): void
    {
        $category = ProductCategory::query()->create([
            'name' => 'Panel Beton',
        ]);
        $unit = Unit::query()->create([
            'code' => 'PCS',
            'name' => 'Pieces',
        ]);

        $this->postJson('/api/master-data/products', [
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'sku' => 'PNL-API-001',
            'name' => 'Panel API',
            'cost_price' => 100000,
            'selling_price' => 125000,
            'min_stock' => 5,
            'stock_status' => 'safe',
        ])
            ->assertCreated()
            ->assertJsonPath('data.category.name', 'Panel Beton')
            ->assertJsonPath('data.unit.code', 'PCS');
    }

    public function test_unknown_master_data_resource_returns_not_found(): void
    {
        $this->getJson('/api/master-data/unknown')
            ->assertNotFound();
    }
}
