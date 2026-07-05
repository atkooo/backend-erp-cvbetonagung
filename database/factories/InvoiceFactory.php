<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_number' => 'INV-TEST-' . $this->faker->unique()->numerify('####'),
            'customer_id'    => Customer::factory(),
            'invoice_date'   => $this->faker->date(),
            'due_date'       => $this->faker->dateTimeBetween('+7 days', '+30 days')->format('Y-m-d'),
            'subtotal'       => 1000000,
            'tax_amount'     => 110000,
            'total'          => 1110000,
            'paid_amount'    => 0,
            'status'         => 'unpaid',
        ];
    }
}
