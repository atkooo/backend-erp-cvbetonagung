<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class GoodsReceiptNoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'grn_number' => 'GRN-TEST-'.$this->faker->unique()->numerify('####'),
            'purchase_order_id' => PurchaseOrder::factory(),
            'warehouse_id' => null,
            'to_location_id' => null,
            'received_by' => null,
            'receipt_date' => $this->faker->date(),
            'status' => 'received',
            'notes' => null,
        ];
    }
}
