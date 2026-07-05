<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliveryOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'delivery_number' => 'DO-TEST-' . $this->faker->unique()->numerify('####'),
            'sales_order_id'  => SalesOrder::factory(),
            'customer_id'     => Customer::factory(),
            'delivery_date'   => $this->faker->date(),
            'status'          => 'ready_to_load',
        ];
    }
}
