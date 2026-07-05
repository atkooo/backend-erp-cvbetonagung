<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payment_number' => 'PAY-TEST-' . $this->faker->unique()->numerify('####'),
            'invoice_id'     => Invoice::factory(),
            'payment_date'   => now(),
            'method'         => 'transfer',
            'amount'         => 500000,
            'status'         => 'pending',
        ];
    }
}
