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

class TrialProductSeeder extends Seeder
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

        // 3. Define the detailed product list
        $products = [
            // Ready-Mix
            [
                'sku' => 'RDX-K225',
                'name' => 'Beton Ready-Mix K-225',
                'category' => 'Ready-Mix',
                'unit' => 'M3',
                'cost_price' => 700000,
                'selling_price' => 820000,
                'min_stock' => 0,
            ],
            [
                'sku' => 'RDX-K250',
                'name' => 'Beton Ready-Mix K-250',
                'category' => 'Ready-Mix',
                'unit' => 'M3',
                'cost_price' => 730000,
                'selling_price' => 850000,
                'min_stock' => 0,
            ],
            [
                'sku' => 'RDX-K300',
                'name' => 'Beton Ready-Mix K-300',
                'category' => 'Ready-Mix',
                'unit' => 'M3',
                'cost_price' => 770000,
                'selling_price' => 900000,
                'min_stock' => 0,
            ],
            [
                'sku' => 'RDX-K350',
                'name' => 'Beton Ready-Mix K-350',
                'category' => 'Ready-Mix',
                'unit' => 'M3',
                'cost_price' => 810000,
                'selling_price' => 950000,
                'min_stock' => 0,
            ],
            [
                'sku' => 'RDX-K400',
                'name' => 'Beton Ready-Mix K-400',
                'category' => 'Ready-Mix',
                'unit' => 'M3',
                'cost_price' => 860000,
                'selling_price' => 1020000,
                'min_stock' => 0,
            ],

            // Precast
            [
                'sku' => 'PRC-UD30',
                'name' => 'Saluran U-Ditch 30x30x120cm',
                'category' => 'Beton Precast',
                'unit' => 'PCS',
                'cost_price' => 110000,
                'selling_price' => 165000,
                'min_stock' => 50,
            ],
            [
                'sku' => 'PRC-UD40',
                'name' => 'Saluran U-Ditch 40x40x120cm',
                'category' => 'Beton Precast',
                'unit' => 'PCS',
                'cost_price' => 150000,
                'selling_price' => 220000,
                'min_stock' => 40,
            ],
            [
                'sku' => 'PRC-CUD30',
                'name' => 'Tutup/Cover U-Ditch 30cm Light Duty',
                'category' => 'Beton Precast',
                'unit' => 'PCS',
                'cost_price' => 35000,
                'selling_price' => 55000,
                'min_stock' => 100,
            ],
            [
                'sku' => 'PRC-CUD40',
                'name' => 'Tutup/Cover U-Ditch 40cm Heavy Duty',
                'category' => 'Beton Precast',
                'unit' => 'PCS',
                'cost_price' => 55000,
                'selling_price' => 85000,
                'min_stock' => 80,
            ],
            [
                'sku' => 'PRC-PVB6',
                'name' => 'Paving Block Bata 6cm Abu-Abu',
                'category' => 'Beton Precast',
                'unit' => 'PCS',
                'cost_price' => 800,
                'selling_price' => 1250,
                'min_stock' => 5000,
            ],
            [
                'sku' => 'PRC-PVB8',
                'name' => 'Paving Block Bata 8cm Merah',
                'category' => 'Beton Precast',
                'unit' => 'PCS',
                'cost_price' => 1100,
                'selling_price' => 1700,
                'min_stock' => 3000,
            ],
            [
                'sku' => 'PRC-KNST',
                'name' => 'Kanstin Beton Pembatas Jalan 40x20x10cm',
                'category' => 'Beton Precast',
                'unit' => 'PCS',
                'cost_price' => 12000,
                'selling_price' => 18500,
                'min_stock' => 150,
            ],

            // GRC & Ornamen
            [
                'sku' => 'GRC-DOM5',
                'name' => 'Kubah GRC Diameter 5 Meter Motif Klasik',
                'category' => 'GRC & Ornamen',
                'unit' => 'SET',
                'cost_price' => 8500000,
                'selling_price' => 13500000,
                'min_stock' => 2,
            ],
            [
                'sku' => 'GRC-DOM8',
                'name' => 'Kubah GRC Diameter 8 Meter Motif Bintang',
                'category' => 'GRC & Ornamen',
                'unit' => 'SET',
                'cost_price' => 19500000,
                'selling_price' => 28000000,
                'min_stock' => 1,
            ],
            [
                'sku' => 'ORN-CLG01',
                'name' => 'Ornamen Kaligrafi GRC Timbul',
                'category' => 'GRC & Ornamen',
                'unit' => 'MTR',
                'cost_price' => 90000,
                'selling_price' => 140000,
                'min_stock' => 20,
            ],

            // Raw Material
            [
                'sku' => 'RAW-CMT50',
                'name' => 'Semen Portland Gresik 50kg',
                'category' => 'Material Baku',
                'unit' => 'SAK',
                'cost_price' => 58000,
                'selling_price' => 64000,
                'min_stock' => 100,
            ],
            [
                'sku' => 'RAW-SND01',
                'name' => 'Pasir Cor Semeru Lumajang',
                'category' => 'Material Baku',
                'unit' => 'TRIP',
                'cost_price' => 1200000,
                'selling_price' => 1400000,
                'min_stock' => 5,
            ],
            [
                'sku' => 'RAW-IRN08',
                'name' => 'Besi Beton Ulir Dia 8mm SNI',
                'category' => 'Material Baku',
                'unit' => 'PCS',
                'cost_price' => 48000,
                'selling_price' => 53000,
                'min_stock' => 200,
            ],
            [
                'sku' => 'RAW-IRN10',
                'name' => 'Besi Beton Ulir Dia 10mm SNI',
                'category' => 'Material Baku',
                'unit' => 'PCS',
                'cost_price' => 72000,
                'selling_price' => 78500,
                'min_stock' => 150,
            ],
            [
                'sku' => 'RAW-ADSP',
                'name' => 'Cairan Superplasticizer Admixture',
                'category' => 'Material Baku',
                'unit' => 'KG',
                'cost_price' => 18000,
                'selling_price' => 22000,
                'min_stock' => 50,
            ],
        ];

        foreach ($products as $p) {
            $category = ProductCategory::query()->where('name', $p['category'])->first();
            $unit = Unit::query()->where('code', $p['unit'])->first();

            Product::query()->updateOrCreate(
                ['sku' => $p['sku']],
                [
                    'category_id' => $category?->id,
                    'unit_id' => $unit?->id,
                    'name' => $p['name'],
                    'cost_price' => $p['cost_price'],
                    'selling_price' => $p['selling_price'],
                    'min_stock' => $p['min_stock'],
                    'stock_status' => 'safe',
                    'qr_value' => $p['sku'],
                    'status' => 'active',
                ]
            );
        }

        // 4. Map stock locations
        $gdgUtm = Warehouse::query()->where('code', 'GDG-UTM')->first();
        $gdgJdi = Warehouse::query()->where('code', 'GDG-JDI')->first() ?? $gdgUtm;
        $gdgPrd = Warehouse::query()->where('code', 'GDG-PRD')->first() ?? Warehouse::query()->where('code', 'WRK-PRD')->first() ?? $gdgUtm;

        if (!$gdgUtm) {
            return; // Exit if no warehouse exists at all
        }

        $locJdi = StorageLocation::query()->where('warehouse_id', $gdgJdi->id)->whereIn('code', ['JDI-A1', 'DEFAULT'])->first();
        $locUtm = StorageLocation::query()->where('warehouse_id', $gdgUtm->id)->whereIn('code', ['UTM-A1', 'DEFAULT'])->first();
        $locPrd = StorageLocation::query()->where('warehouse_id', $gdgPrd->id)->whereIn('code', ['PRD-MAT', 'DEFAULT'])->first();

        $admin = User::query()->where('email', 'admin@example.com')->first();

        // 5. Initial Stocks mapping
        $stocks = [
            ['sku' => 'PRC-UD30', 'loc' => $locJdi, 'qty' => 150],
            ['sku' => 'PRC-UD40', 'loc' => $locJdi, 'qty' => 80],
            ['sku' => 'PRC-CUD30', 'loc' => $locJdi, 'qty' => 200],
            ['sku' => 'PRC-CUD40', 'loc' => $locJdi, 'qty' => 120],
            ['sku' => 'PRC-PVB6', 'loc' => $locUtm, 'qty' => 8500],
            ['sku' => 'PRC-PVB8', 'loc' => $locUtm, 'qty' => 4000],
            ['sku' => 'PRC-KNST', 'loc' => $locUtm, 'qty' => 300],
            ['sku' => 'GRC-DOM5', 'loc' => $locJdi, 'qty' => 3],
            ['sku' => 'RAW-CMT50', 'loc' => $locPrd, 'qty' => 500],
            ['sku' => 'RAW-SND01', 'loc' => $locPrd, 'qty' => 15],
            ['sku' => 'RAW-IRN08', 'loc' => $locPrd, 'qty' => 450],
            ['sku' => 'RAW-IRN10', 'loc' => $locPrd, 'qty' => 300],
            ['sku' => 'RAW-ADSP', 'loc' => $locPrd, 'qty' => 250],
        ];

        foreach ($stocks as $s) {
            $product = Product::query()->where('sku', $s['sku'])->first();
            $location = $s['loc'];

            if ($product && $location) {
                ProductStock::query()->updateOrCreate(
                    ['product_id' => $product->id, 'location_id' => $location->id],
                    ['quantity' => $s['qty']]
                );

                StockMovement::query()->updateOrCreate(
                    [
                        'reference_type' => 'seed',
                        'reference_number' => 'SEED-TRIAL-' . $product->sku . '-' . $location->code,
                        'product_id' => $product->id,
                    ],
                    [
                        'to_location_id' => $location->id,
                        'type' => 'in',
                        'quantity' => $s['qty'],
                        'handled_by' => $admin?->id,
                        'notes' => 'Trial initial stock.',
                        'movement_at' => now(),
                    ]
                );
            }
        }
    }
}
