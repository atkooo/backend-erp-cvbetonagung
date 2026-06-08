<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStock;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * Seed practical master data for development/demo environments.
     */
    public function run(): void
    {
        $units = [
            ['code' => 'PCS', 'name' => 'Pieces'],
            ['code' => 'MTR', 'name' => 'Meter'],
            ['code' => 'SET', 'name' => 'Set'],
            ['code' => 'KG', 'name' => 'Kilogram'],
            ['code' => 'SAK', 'name' => 'Sak'],
        ];

        foreach ($units as $unit) {
            Unit::query()->updateOrCreate(['code' => $unit['code']], $unit);
        }

        $categories = [
            ['name' => 'Roster', 'description' => 'Produk roster beton untuk ventilasi dan dekorasi bangunan.', 'status' => 'active'],
            ['name' => 'Lisplang', 'description' => 'Lisplang beton berbagai ukuran dan motif.', 'status' => 'active'],
            ['name' => 'Ornamen Beton', 'description' => 'Ornamen beton dekoratif untuk fasad, taman, dan proyek custom.', 'status' => 'active'],
            ['name' => 'Kubah Masjid', 'description' => 'Komponen kubah dan aksesori masjid berbasis beton atau GRC.', 'status' => 'active'],
            ['name' => 'Bahan Baku', 'description' => 'Material produksi seperti semen, pasir, besi, dan bahan pendukung.', 'status' => 'active'],
        ];

        foreach ($categories as $category) {
            ProductCategory::query()->updateOrCreate(['name' => $category['name']], $category);
        }

        $warehouses = [
            ['code' => 'GDG-UTM', 'name' => 'Gudang Utama', 'type' => 'warehouse', 'address' => 'Area penyimpanan utama CV Beton Agung', 'status' => 'active'],
            ['code' => 'GDG-PRD', 'name' => 'Gudang Produksi', 'type' => 'workshop', 'address' => 'Area dekat workshop produksi', 'status' => 'active'],
            ['code' => 'GDG-JDI', 'name' => 'Gudang Barang Jadi', 'type' => 'warehouse', 'address' => 'Area penyimpanan produk siap kirim', 'status' => 'active'],
        ];

        foreach ($warehouses as $warehouse) {
            Warehouse::query()->updateOrCreate(['code' => $warehouse['code']], $warehouse);
        }

        $locations = [
            ['warehouse_code' => 'GDG-UTM', 'code' => 'UTM-A1', 'name' => 'Rak Utama A1', 'description' => 'Rak utama untuk produk fast moving'],
            ['warehouse_code' => 'GDG-UTM', 'code' => 'UTM-B1', 'name' => 'Rak Utama B1', 'description' => 'Rak untuk produk ukuran panjang'],
            ['warehouse_code' => 'GDG-PRD', 'code' => 'PRD-MAT', 'name' => 'Area Material Produksi', 'description' => 'Lokasi bahan baku produksi harian'],
            ['warehouse_code' => 'GDG-PRD', 'code' => 'PRD-QC', 'name' => 'Area QC', 'description' => 'Lokasi transit barang selesai produksi sebelum masuk gudang jadi'],
            ['warehouse_code' => 'GDG-JDI', 'code' => 'JDI-A1', 'name' => 'Rak Barang Jadi A1', 'description' => 'Produk jadi siap kirim'],
        ];

        foreach ($locations as $location) {
            $warehouse = Warehouse::query()->where('code', $location['warehouse_code'])->first();
            if ($warehouse === null) {
                continue;
            }

            StorageLocation::query()->updateOrCreate(
                ['warehouse_id' => $warehouse->id, 'code' => $location['code']],
                ['name' => $location['name'], 'description' => $location['description']],
            );
        }

        $suppliers = [
            ['code' => 'SUP-001', 'name' => 'PT Semen Nusantara', 'contact_name' => 'Budi Santoso', 'phone' => '081234567001', 'city' => 'Gresik', 'address' => 'Jl. Industri Semen No. 12, Gresik', 'status' => 'active'],
            ['code' => 'SUP-002', 'name' => 'CV Pasir Lumajang Makmur', 'contact_name' => 'Slamet Riyadi', 'phone' => '081234567002', 'city' => 'Lumajang', 'address' => 'Jl. Tambang Pasir No. 8, Lumajang', 'status' => 'active'],
            ['code' => 'SUP-003', 'name' => 'Toko Besi Sumber Jaya', 'contact_name' => 'Hendra Wijaya', 'phone' => '081234567003', 'city' => 'Sidoarjo', 'address' => 'Jl. Raya Taman No. 45, Sidoarjo', 'status' => 'active'],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::query()->updateOrCreate(['code' => $supplier['code']], $supplier);
        }

        $customers = [
            ['code' => 'CUST-001', 'name' => 'H. Ahmad Syukur', 'phone' => '081234568001', 'email' => 'ahmad.syukur@example.com', 'city' => 'Sidoarjo', 'address' => 'Jl. Masjid Al-Ikhlas No. 10, Sidoarjo', 'status' => 'active'],
            ['code' => 'CUST-002', 'name' => 'Takmir Masjid Baiturrahman', 'phone' => '081234568002', 'email' => 'baiturrahman@example.com', 'city' => 'Surabaya', 'address' => 'Jl. Kebonsari No. 22, Surabaya', 'status' => 'active'],
            ['code' => 'CUST-003', 'name' => 'Mandor Joko Prasetyo', 'phone' => '081234568003', 'email' => 'joko.prasetyo@example.com', 'city' => 'Mojokerto', 'address' => 'Jl. Raya Mojosari No. 18, Mojokerto', 'status' => 'active'],
        ];

        foreach ($customers as $customer) {
            Customer::query()->updateOrCreate(['code' => $customer['code']], $customer);
        }

        $products = [
            ['sku' => 'RST-KS-001', 'name' => 'Roster Beton Kotak Silang', 'category' => 'Roster', 'unit' => 'PCS', 'cost_price' => 9500, 'selling_price' => 15000, 'min_stock' => 100],
            ['sku' => 'RST-MN-002', 'name' => 'Roster Beton Minimalis', 'category' => 'Roster', 'unit' => 'PCS', 'cost_price' => 11000, 'selling_price' => 17500, 'min_stock' => 80],
            ['sku' => 'LSP-KL-030', 'name' => 'Lisplang Beton Klasik 30cm', 'category' => 'Lisplang', 'unit' => 'MTR', 'cost_price' => 45000, 'selling_price' => 70000, 'min_stock' => 50],
            ['sku' => 'ORN-PIL-001', 'name' => 'Ornamen Pilar Beton Dekoratif', 'category' => 'Ornamen Beton', 'unit' => 'SET', 'cost_price' => 180000, 'selling_price' => 275000, 'min_stock' => 10],
            ['sku' => 'KUB-GRC-006', 'name' => 'Kubah Masjid GRC Diameter 6m', 'category' => 'Kubah Masjid', 'unit' => 'SET', 'cost_price' => 12500000, 'selling_price' => 18500000, 'min_stock' => 1],
            ['sku' => 'BBK-SMN-050', 'name' => 'Semen Portland 50kg', 'category' => 'Bahan Baku', 'unit' => 'SAK', 'cost_price' => 59000, 'selling_price' => 65000, 'min_stock' => 40],
            ['sku' => 'BBK-PSR-001', 'name' => 'Pasir Cor Halus', 'category' => 'Bahan Baku', 'unit' => 'KG', 'cost_price' => 350, 'selling_price' => 500, 'min_stock' => 1000],
        ];

        foreach ($products as $product) {
            $category = ProductCategory::query()->where('name', $product['category'])->first();
            $unit = Unit::query()->where('code', $product['unit'])->first();

            Product::query()->updateOrCreate(
                ['sku' => $product['sku']],
                [
                    'category_id' => $category?->id,
                    'unit_id' => $unit?->id,
                    'name' => $product['name'],
                    'cost_price' => $product['cost_price'],
                    'selling_price' => $product['selling_price'],
                    'min_stock' => $product['min_stock'],
                    'stock_status' => 'safe',
                    'qr_value' => $product['sku'],
                    'status' => 'active',
                ],
            );
        }

        $initialStocks = [
            ['sku' => 'RST-KS-001', 'location_code' => 'JDI-A1', 'quantity' => 250],
            ['sku' => 'RST-MN-002', 'location_code' => 'JDI-A1', 'quantity' => 120],
            ['sku' => 'LSP-KL-030', 'location_code' => 'UTM-B1', 'quantity' => 80],
            ['sku' => 'ORN-PIL-001', 'location_code' => 'UTM-A1', 'quantity' => 16],
            ['sku' => 'KUB-GRC-006', 'location_code' => 'JDI-A1', 'quantity' => 2],
            ['sku' => 'BBK-SMN-050', 'location_code' => 'PRD-MAT', 'quantity' => 75],
            ['sku' => 'BBK-PSR-001', 'location_code' => 'PRD-MAT', 'quantity' => 2500],
        ];

        foreach ($initialStocks as $stock) {
            $product = Product::query()->where('sku', $stock['sku'])->first();
            $location = StorageLocation::query()->where('code', $stock['location_code'])->first();

            if ($product === null || $location === null) {
                continue;
            }

            ProductStock::query()->updateOrCreate(
                ['product_id' => $product->id, 'location_id' => $location->id],
                ['quantity' => $stock['quantity']],
            );
        }

        $employees = [
            [
                'employee_number' => 'EMP-ADM-001',
                'email' => 'admin@example.com',
                'name' => 'System Administrator',
                'role_name' => 'Administrator',
                'department' => 'Management',
                'employee_type' => 'permanent',
            ],
            [
                'employee_number' => 'EMP-INV-001',
                'email' => 'inventory@example.com',
                'name' => 'Inventory User',
                'role_name' => 'Kepala Gudang',
                'department' => 'Gudang',
                'employee_type' => 'permanent',
            ],
            [
                'employee_number' => 'EMP-PUR-001',
                'email' => 'purchasing@example.com',
                'name' => 'Purchasing User',
                'role_name' => 'Purchasing',
                'department' => 'Purchasing',
                'employee_type' => 'permanent',
            ],
            [
                'employee_number' => 'EMP-BIL-001',
                'email' => 'billing@example.com',
                'name' => 'Billing User',
                'role_name' => 'Billing AR',
                'department' => 'Finance',
                'employee_type' => 'permanent',
            ],
            [
                'employee_number' => 'EMP-CAS-001',
                'email' => 'cashier@example.com',
                'name' => 'Cashier User',
                'role_name' => 'Kasir',
                'department' => 'Finance',
                'employee_type' => 'permanent',
            ],
            [
                'employee_number' => 'EMP-PRD-001',
                'email' => 'production@example.com',
                'name' => 'Production User',
                'role_name' => 'Operator Produksi',
                'department' => 'Produksi',
                'employee_type' => 'permanent',
            ],
        ];

        foreach ($employees as $employee) {
            $user = User::query()->where('email', $employee['email'])->first();

            Employee::query()->updateOrCreate(
                ['employee_number' => $employee['employee_number']],
                [
                    'user_id' => $user?->id,
                    'name' => $employee['name'],
                    'role_name' => $employee['role_name'],
                    'department' => $employee['department'],
                    'phone' => null,
                    'address' => null,
                    'join_date' => null,
                    'employee_type' => $employee['employee_type'],
                    'daily_rate' => 0,
                    'piece_rate' => 0,
                    'status' => 'active',
                ],
            );
        }
    }
}
