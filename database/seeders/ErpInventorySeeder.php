<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\StockOpnameSession;
use App\Models\StorageLocation;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class ErpInventorySeeder extends Seeder
{
    /**
     * Seed baseline stock records for existing products and locations.
     */
    public function run(): void
    {
        $defaultLocation = StorageLocation::query()
            ->where('code', 'DEFAULT')
            ->whereHas('warehouse', fn ($query) => $query->where('code', 'GDG-UTM'))
            ->first();

        if ($defaultLocation === null) {
            return;
        }

        Product::query()->each(function (Product $product) use ($defaultLocation): void {
            ProductStock::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'location_id' => $defaultLocation->id,
                ],
                ['quantity' => 0],
            );
        });

        $admin = User::query()->where('email', 'admin@example.com')->first();
        $product = Product::query()->where('sku', 'PRC-0001')->first();

        if ($product !== null) {
            StockMovement::query()->updateOrCreate(
                [
                    'reference_type' => 'manual',
                    'reference_number' => 'INIT-STOCK',
                    'product_id' => $product->id,
                ],
                [
                    'to_location_id' => $defaultLocation->id,
                    'type' => 'in',
                    'quantity' => 0,
                    'handled_by' => $admin?->id,
                    'notes' => 'Initial stock baseline.',
                    'movement_at' => now(),
                ],
            );
        }

        $warehouse = Warehouse::query()->where('code', 'GDG-UTM')->first();

        if ($warehouse !== null) {
            StockOpnameSession::query()->updateOrCreate(
                ['opname_number' => 'OPN-INIT'],
                [
                    'warehouse_id' => $warehouse->id,
                    'started_by' => $admin?->id,
                    'status' => 'draft',
                    'started_at' => now(),
                    'closed_at' => null,
                    'notes' => 'Initial stock opname session.',
                ],
            );
        }
    }
}
