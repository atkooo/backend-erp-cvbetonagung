<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\Product;
use App\Models\StorageLocation;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpMasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_data_seed_creates_baseline_records(): void
    {
        $this->seed();

        $this->assertDatabaseHas('roles', ['code' => 'admin']);
        $this->assertDatabaseHas('users', ['email' => 'admin@example.com']);
        $this->assertDatabaseHas('units', ['code' => 'PCS']);
        $this->assertDatabaseHas('warehouses', ['code' => 'GDG-UTM']);
        $this->assertDatabaseHas('storage_locations', ['code' => 'DEFAULT']);
        $this->assertDatabaseHas('products', ['sku' => 'PRC-0001']);
        $this->assertDatabaseHas('company_settings', ['company_name' => 'CV Beton Agung']);
    }

    public function test_product_relations_are_available(): void
    {
        $this->seed();

        $product = Product::query()
            ->where('sku', 'PRC-0001')
            ->firstOrFail();

        $this->assertSame('Beton Precast', $product->category?->name);
        $this->assertSame('PCS', $product->unit?->code);
    }

    public function test_warehouse_storage_location_relation_is_available(): void
    {
        $this->seed();

        $warehouse = Warehouse::query()
            ->where('code', 'GDG-UTM')
            ->firstOrFail();

        $this->assertTrue($warehouse->storageLocations->contains('code', 'DEFAULT'));
        $this->assertInstanceOf(Warehouse::class, StorageLocation::query()->firstOrFail()->warehouse);
    }

    public function test_company_setting_casts_tax_rate_as_decimal_string(): void
    {
        $this->seed();

        $setting = CompanySetting::query()->firstOrFail();

        $this->assertSame('0.00', $setting->tax_rate);
        $this->assertGreaterThan(0, Unit::query()->count());
    }
}
