<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'po_number'   => 'PO-TEST-' . $this->faker->unique()->numerify('####'),
            'supplier_id' => Supplier::factory(),
            'po_date'     => $this->faker->date(),
            'total'       => 500000,
            'status'      => 'draft',
        ];
    }
}
