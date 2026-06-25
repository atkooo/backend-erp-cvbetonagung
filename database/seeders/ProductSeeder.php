<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Ensure required Units exist
        $units = [
            ['code' => 'PCS', 'name' => 'Pieces'],
            ['code' => 'MTR', 'name' => 'Meter'],
            ['code' => 'SET', 'name' => 'Set'],
            ['code' => 'KG', 'name' => 'Kilogram'],
            ['code' => 'SAK', 'name' => 'Sak'],
            ['code' => 'TRIP', 'name' => 'Trip'],
            ['code' => 'M3', 'name' => 'Meter Kubik'],
        ];

        foreach ($units as $unit) {
            Unit::query()->firstOrCreate(['code' => $unit['code']], $unit);
        }

        // 2. Ensure required Categories exist
        $categories = [
            ['name' => 'Ready-Mix', 'description' => 'Beton segar siap tuang berbagai mutu.', 'status' => 'active'],
            ['name' => 'Beton Precast', 'description' => 'Produk beton siap pasang.', 'status' => 'active'],
            ['name' => 'GRC & Ornamen', 'description' => 'Produk dekorasi bangunan berbasis GRC.', 'status' => 'active'],
            ['name' => 'Material Baku', 'description' => 'Bahan baku untuk produksi beton.', 'status' => 'active'],
            ['name' => 'Jasa', 'description' => 'Layanan dan jasa konstruksi.', 'status' => 'active'],
        ];

        foreach ($categories as $cat) {
            ProductCategory::query()->firstOrCreate(['name' => $cat['name']], $cat);
        }

        // 3. Read products from CSV
        $csvPath = base_path('docs/Produk_GoLive_Data_ERP_CVBetonAgung.csv');
        if (!file_exists($csvPath)) {
            $this->command->warn("CSV file not found at {$csvPath}. Skipping product seed from CSV.");
            return;
        }

        $file = fopen($csvPath, 'r');
        $row = 0;
        $skuCounter = 1;

        $admin = User::query()->where('email', 'admin@example.com')->first();
        $gdgUtm = Warehouse::query()->where('code', 'GDG-UTM')->first();
        if ($gdgUtm) {
            $locUtm = StorageLocation::query()->where('warehouse_id', $gdgUtm->id)->whereIn('code', ['UTM-A1', 'DEFAULT'])->first();
        }

        while (($data = fgetcsv($file, 1000, ';')) !== false) {
            $row++;
            if ($row <= 5) {
                continue; // Skip the first 4 lines of headers + 1 line of column headers
            }

            if (empty(trim($data[2]))) {
                continue; // Skip if Name is empty
            }

            $catName = mb_convert_encoding(trim($data[0]), 'UTF-8', 'ISO-8859-1') ?: 'Umum';
            $sku = trim($data[1]);
            $name = mb_convert_encoding(trim($data[2]), 'UTF-8', 'ISO-8859-1');
            $spec = mb_convert_encoding(trim($data[3]), 'UTF-8', 'ISO-8859-1');
            $unitCode = strtoupper(trim($data[4])) ?: 'PCS';
            
            $costPriceStr = trim($data[5]);
            $sellingPriceStr = trim($data[6]);
            
            $costPrice = (int)str_replace(['.', ','], '', $costPriceStr);
            $sellingPrice = (int)str_replace(['.', ','], '', $sellingPriceStr);

            if ($costPrice == 0 && $sellingPrice > 0) {
                $costPrice = (int)($sellingPrice * 0.8); // Default assumption if cost price is missing
            }

            $initialStock = (int)trim($data[7]);
            $minStock = (int)trim($data[8]);
            
            if (empty($sku)) {
                $sku = 'PRD' . str_pad($skuCounter++, 4, '0', STR_PAD_LEFT);
            }

            // Ensure category exists
            $category = ProductCategory::query()->firstOrCreate(
                ['name' => $catName],
                ['description' => $catName, 'status' => 'active']
            );

            // Ensure unit exists
            $unit = Unit::query()->firstOrCreate(
                ['code' => $unitCode],
                ['name' => $unitCode]
            );

            // Create product
            $product = Product::query()->updateOrCreate(
                ['sku' => $sku],
                [
                    'category_id' => $category->id,
                    'unit_id' => $unit->id,
                    'sku' => $sku,
                    'type' => 'finished_good',
                    'name' => $name . ($spec ? " ($spec)" : ""),
                    'cost_price' => $costPrice,
                    'selling_price' => $sellingPrice,
                    'min_stock' => $minStock,
                    'stock_status' => 'safe',
                    'qr_value' => $sku,
                    'status' => 'active',
                ]
            );

            // Create initial stock if $> 0
            if ($initialStock > 0 && isset($locUtm)) {
                ProductStock::query()->updateOrCreate(
                    ['product_id' => $product->id, 'location_id' => $locUtm->id],
                    ['quantity' => $initialStock]
                );

                StockMovement::query()->updateOrCreate(
                    [
                        'reference_type' => 'seed',
                        'reference_number' => 'SEED-TRIAL-' . $product->sku . '-' . $locUtm->code,
                        'product_id' => $product->id,
                    ],
                    [
                        'to_location_id' => $locUtm->id,
                        'type' => 'in',
                        'quantity' => $initialStock,
                        'handled_by' => $admin?->id,
                        'notes' => 'Stok awal dari data Go Live.',
                        'movement_at' => now(),
                    ]
                );
            }
        }
        fclose($file);
    }
}
