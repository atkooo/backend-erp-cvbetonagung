<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierPayableFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payable_number'    => 'AP-TEST-' . $this->faker->unique()->numerify('####'),
            'supplier_id'       => Supplier::factory(),
            'purchase_order_id' => PurchaseOrder::factory(),
            'due_date'          => $this->faker->dateTimeBetween('+7 days', '+30 days')->format('Y-m-d'),
            'amount'            => 500000,
            'paid_amount'       => 0,
            'status'            => 'open',
        ];
    }
}
