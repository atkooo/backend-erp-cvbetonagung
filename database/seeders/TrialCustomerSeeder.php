<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class TrialCustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = [
            [
                'code' => 'CUST-WKA01',
                'name' => 'PT Wijaya Karya Agung',
                'phone' => '0318947583',
                'email' => 'info@wijayakaryaagung.co.id',
                'city' => 'Surabaya',
                'address' => 'Jl. Raya Darmo No. 101, Surabaya',
                'status' => 'active',
            ],
            [
                'code' => 'CUST-CMK02',
                'name' => 'CV Citra Mandiri Kontraktor',
                'phone' => '081223344556',
                'email' => 'contact@citramandiri.net',
                'city' => 'Sidoarjo',
                'address' => 'Perumahan Pondok Jati Blok AE-12, Sidoarjo',
                'status' => 'active',
            ],
            [
                'code' => 'CUST-MSJ03',
                'name' => 'Takmir Masjid Al-Amanah',
                'phone' => '081398765432',
                'email' => 'alamanah.gresik@gmail.com',
                'city' => 'Gresik',
                'address' => 'Jl. Sunan Giri No. 45, Kebomas, Gresik',
                'status' => 'active',
            ],
            [
                'code' => 'CUST-MSJ04',
                'name' => 'Panitia Pembangunan Masjid Ar-Rahman',
                'phone' => '082155667788',
                'email' => 'arrahman.malang@yahoo.com',
                'city' => 'Malang',
                'address' => 'Jl. Sukarno Hatta No. 88, Lowokwaru, Malang',
                'status' => 'active',
            ],
            [
                'code' => 'CUST-DPU05',
                'name' => 'Dinas Pekerjaan Umum Cipta Karya',
                'phone' => '0315312345',
                'email' => 'dinaspu@jatimprov.go.id',
                'city' => 'Surabaya',
                'address' => 'Jl. Gayung Kebonsari No. 167, Surabaya',
                'status' => 'active',
            ],
            [
                'code' => 'CUST-APL06',
                'name' => 'PT Agung Podomoro Land Tbk',
                'phone' => '02129033000',
                'email' => 'corporate@agungpodomoroland.com',
                'city' => 'Jakarta',
                'address' => 'APL Tower Lt. 43, Jl. Letjen S. Parman, Jakarta Barat',
                'status' => 'active',
            ],
        ];

        foreach ($customers as $customer) {
            Customer::query()->updateOrCreate(['code' => $customer['code']], $customer);
        }
    }
}
