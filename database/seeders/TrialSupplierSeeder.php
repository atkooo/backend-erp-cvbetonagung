<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class TrialSupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            [
                'code' => 'SUP-LMA01',
                'name' => 'CV Lumajang Aggregates',
                'contact_name' => 'Agus Hermawan',
                'phone' => '081122334455',
                'city' => 'Lumajang',
                'address' => 'Jl. Tambang Pasir Semeru No. 9, Lumajang',
                'status' => 'active',
            ],
            [
                'code' => 'SUP-KSD02',
                'name' => 'PT Krakatau Steel Distributor',
                'contact_name' => 'Yusuf Wibowo',
                'phone' => '0254372111',
                'city' => 'Cilegon',
                'address' => 'Kawasan Industri Krakatau Steel, Cilegon',
                'status' => 'active',
            ],
            [
                'code' => 'SUP-SCI03',
                'name' => 'Sika Chemical Indonesia',
                'contact_name' => 'Lina Marlina',
                'phone' => '0218200123',
                'city' => 'Bekasi',
                'address' => 'Jl. Kawasan Industri MM2100 Blok C-3, Cibitung, Bekasi',
                'status' => 'active',
            ],
            [
                'code' => 'SUP-ITP04',
                'name' => 'PT Indocement Tunggal Prakarsa',
                'contact_name' => 'Rahmat Hidayat',
                'phone' => '0231341234',
                'city' => 'Cirebon',
                'address' => 'Jl. Raya Cirebon-Bandung Km. 20, Palimanan, Cirebon',
                'status' => 'active',
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::query()->updateOrCreate(['code' => $supplier['code']], $supplier);
        }
    }
}
