<?php

namespace Database\Factories;

use App\Models\DeliveryOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliveryOrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'delivery_order_id' => DeliveryOrder::factory(),
            'sales_order_item_id' => null,
            'product_id' => null, // Must be provided explicitly in tests
            'quantity' => $this->faker->randomFloat(2, 1, 50),
        ];
    }
}
