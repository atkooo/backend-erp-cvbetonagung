<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class RfqFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rfq_number' => 'RFQ-TEST-'.$this->faker->unique()->numerify('####'),
            'supplier_id' => Supplier::factory(),
            'rfq_date' => $this->faker->date(),
            'valid_until' => $this->faker->dateTimeBetween('+1 week', '+1 month')->format('Y-m-d'),
            'status' => 'draft',
            'notes' => null,
        ];
    }
}
