<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_number' => 'SO-TEST-'.$this->faker->unique()->numerify('####'),
            'customer_id' => Customer::factory(),
            'order_date' => $this->faker->date(),
            'total' => 1000000,
            'status' => 'draft',
        ];
    }
}
