<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuotationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'quotation_number' => 'QUO-TEST-'.$this->faker->unique()->numerify('####'),
            'customer_id' => Customer::factory(),
            'quotation_date' => $this->faker->date(),
            'valid_until' => $this->faker->dateTimeBetween('+7 days', '+30 days')->format('Y-m-d'),
            'subtotal' => 1000000,
            'tax_amount' => 110000,
            'total' => 1110000,
            'status' => 'draft',
        ];
    }
}
