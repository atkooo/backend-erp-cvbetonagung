<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = base_path('docs/Customer_GoLive_Data_ERP_CVBetonAgung.csv');
        if (! file_exists($csvPath)) {
            $this->command->warn("CSV file not found at {$csvPath}.");

            return;
        }

        $file = fopen($csvPath, 'r');
        $row = 0;
        $counter = 1;

        while (($data = fgetcsv($file, 1000, ';')) !== false) {
            $row++;
            if ($row <= 5) {
                continue;
            }

            $name = trim($data[1]);
            if (empty($name)) {
                continue;
            }

            $code = trim($data[0]);
            if (empty($code)) {
                $code = 'CUST'.str_pad($counter++, 4, '0', STR_PAD_LEFT);
            }

            $address = trim($data[2]);
            $city = trim($data[3]);
            $phone = trim($data[4]);
            $email = trim($data[5]);

            $customer = [
                'code' => $code,
                'name' => mb_convert_encoding($name, 'UTF-8', 'ISO-8859-1'),
                'address' => mb_convert_encoding($address, 'UTF-8', 'ISO-8859-1'),
                'city' => mb_convert_encoding($city, 'UTF-8', 'ISO-8859-1'),
                'phone' => $phone ?: '-',
                'email' => $email,
                'status' => 'active',
            ];

            Customer::query()->updateOrCreate(['code' => $code], $customer);
        }
        fclose($file);
    }
}
