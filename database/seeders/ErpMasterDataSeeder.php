<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class ErpMasterDataSeeder extends Seeder
{
    /**
     * Seed baseline ERP master data.
     */
    public function run(): void
    {
        $units = [
            ['code' => 'PCS', 'name' => 'Pcs'],
            ['code' => 'M3', 'name' => 'Meter Kubik'],
            ['code' => 'KG', 'name' => 'Kilogram'],
            ['code' => 'SAK', 'name' => 'Sak'],
            ['code' => 'TRIP', 'name' => 'Trip'],
        ];

        foreach ($units as $unit) {
            Unit::query()->updateOrCreate(['code' => $unit['code']], $unit);
        }

        $categories = [
            [
                'name' => 'Beton Precast',
                'description' => 'Produk beton siap pasang.',
                'status' => 'active',
            ],
            [
                'name' => 'Material Baku',
                'description' => 'Material produksi beton.',
                'status' => 'active',
            ],
            [
                'name' => 'Jasa',
                'description' => 'Layanan pengiriman, pemasangan, dan pekerjaan proyek.',
                'status' => 'active',
            ],
        ];

        foreach ($categories as $category) {
            ProductCategory::query()->updateOrCreate(['name' => $category['name']], $category);
        }

        $mainWarehouse = Warehouse::query()->updateOrCreate(
            ['code' => 'GDG-UTM'],
            [
                'name' => 'Gudang Utama',
                'type' => 'warehouse',
                'address' => 'CV Beton Agung',
                'status' => 'active',
            ],
        );

        $workshop = Warehouse::query()->updateOrCreate(
            ['code' => 'WRK-PRD'],
            [
                'name' => 'Workshop Produksi',
                'type' => 'workshop',
                'address' => 'CV Beton Agung',
                'status' => 'active',
            ],
        );

        foreach ([$mainWarehouse, $workshop] as $warehouse) {
            StorageLocation::query()->updateOrCreate(
                [
                    'warehouse_id' => $warehouse->id,
                    'code' => 'DEFAULT',
                ],
                [
                    'name' => 'Default',
                    'description' => 'Lokasi penyimpanan utama.',
                ],
            );
        }

        Supplier::query()->updateOrCreate(
            ['code' => 'SUP-UMUM'],
            [
                'name' => 'Supplier Umum',
                'contact_name' => 'Admin Supplier',
                'phone' => null,
                'city' => null,
                'address' => null,
                'status' => 'active',
            ],
        );

        $precastCategory = ProductCategory::query()->where('name', 'Beton Precast')->first();
        $materialCategory = ProductCategory::query()->where('name', 'Material Baku')->first();
        $pcsUnit = Unit::query()->where('code', 'PCS')->first();
        $kgUnit = Unit::query()->where('code', 'KG')->first();

        Product::query()->updateOrCreate(
            ['sku' => 'PRC-0001'],
            [
                'category_id' => $precastCategory?->id,
                'unit_id' => $pcsUnit?->id,
                'name' => 'Produk Beton Precast',
                'cost_price' => 0,
                'selling_price' => 0,
                'min_stock' => 0,
                'stock_status' => 'safe',
                'qr_value' => 'PRC-0001',
                'status' => 'active',
            ],
        );

        Product::query()->updateOrCreate(
            ['sku' => 'MTL-0001'],
            [
                'category_id' => $materialCategory?->id,
                'unit_id' => $kgUnit?->id,
                'name' => 'Material Baku Umum',
                'cost_price' => 0,
                'selling_price' => 0,
                'min_stock' => 0,
                'stock_status' => 'safe',
                'qr_value' => 'MTL-0001',
                'status' => 'active',
            ],
        );

        CompanySetting::query()->updateOrCreate(
            ['company_name' => 'CV Beton Agung'],
            [
                'company_address' => null,
                'contact_phone' => null,
                'operational_email' => null,
                'tax_rate' => 0,
                'backup_schedule' => null,
                'updated_by' => null,
            ],
        );
    }
}
