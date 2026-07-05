<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'WH-'.$this->faker->unique()->numerify('###'),
            'name' => 'Gudang '.$this->faker->city(),
            'type' => 'main',
            'address' => $this->faker->address(),
            'status' => 'active',
        ];
    }
}
