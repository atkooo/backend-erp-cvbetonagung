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

class MaterialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = base_path('docs/Bahan_GoLive_Data_ERP_CVBetonAgung.csv');
        if (!file_exists($csvPath)) {
            $this->command->warn("CSV file not found at {$csvPath}. Skipping material seed from CSV.");
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

        $category = ProductCategory::query()->firstOrCreate(
            ['name' => 'Material Baku'],
            ['description' => 'Bahan baku untuk produksi beton.', 'status' => 'active']
        );

        while (($data = fgetcsv($file, 1000, ';')) !== false) {
            $row++;
            if ($row <= 5) {
                continue; // Skip headers
            }

            if (empty(trim($data[1]))) {
                continue; // Skip if Name is empty
            }

            $sku = trim($data[0]);
            $name = mb_convert_encoding(trim($data[1]), 'UTF-8', 'ISO-8859-1');
            $spec = mb_convert_encoding(trim($data[2]), 'UTF-8', 'ISO-8859-1');
            $unitCode = strtoupper(trim($data[3])) ?: 'PCS';
            
            $costPriceStr = trim($data[4]);
            $costPrice = (int)str_replace(['.', ','], '', $costPriceStr);
            
            $initialStockStr = trim($data[5]);
            $initialStock = (int)str_replace(['.', ','], '', $initialStockStr);

            $minStockStr = trim($data[6]);
            $minStock = (int)str_replace(['.', ','], '', $minStockStr);

            if (empty($sku)) {
                $sku = 'MAT-' . str_pad($skuCounter++, 4, '0', STR_PAD_LEFT);
            }

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
                    'type' => 'raw_material',
                    'name' => $name . ($spec ? " ($spec)" : ""),
                    'cost_price' => $costPrice,
                    'selling_price' => 0, // Material doesn't have selling price
                    'min_stock' => $minStock,
                    'stock_status' => 'safe',
                    'qr_value' => $sku,
                    'status' => 'active',
                ]
            );

            // Create initial stock if > 0
            if ($initialStock > 0 && isset($locUtm)) {
                ProductStock::query()->updateOrCreate(
                    ['product_id' => $product->id, 'location_id' => $locUtm->id],
                    ['quantity' => $initialStock]
                );

                StockMovement::query()->updateOrCreate(
                    [
                        'reference_type' => 'seed',
                        'reference_number' => 'SEED-MAT-' . $product->sku . '-' . $locUtm->code,
                        'product_id' => $product->id,
                    ],
                    [
                        'to_location_id' => $locUtm->id,
                        'type' => 'in',
                        'quantity' => $initialStock,
                        'handled_by' => $admin?->id,
                        'notes' => 'Stok awal material dari data Go Live.',
                        'movement_at' => now(),
                    ]
                );
            }
        }
        fclose($file);
    }
}
