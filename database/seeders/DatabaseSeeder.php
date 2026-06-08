<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RbacSeeder::class,
            ErpMasterDataSeeder::class,
            MasterDataSeeder::class,
            ErpInventorySeeder::class,
            ErpSalesSeeder::class,
            ErpProjectSeeder::class,
            ErpFinanceSeeder::class,
            ErpReturnSeeder::class,
            ErpProductionSeeder::class,
            ErpSupportSeeder::class,
        ]);
    }
}
