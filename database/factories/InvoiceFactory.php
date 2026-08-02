<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_number' => fake()->unique()->numerify('INV-######'),
            'client_id' => Client::factory(),
            'created_by_user_id' => User::factory(),
            'status' => 'draft',
            'currency' => 'MWK',
            'issued_at' => today(),
            'due_at' => today()->addDays(30),
            'subtotal' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 0,
            'balance_due' => 0,
        ];
    }
}
