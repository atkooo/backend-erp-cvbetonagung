<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class StorageLocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'name' => 'Lokasi-'.$this->faker->unique()->numerify('###'),
            'code' => 'LOC-'.$this->faker->unique()->numerify('###'),
            'description' => null,
        ];
    }
}
