<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Matter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Matter>
 */
class MatterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'matter_number' => fake()->unique()->numerify('MAT-######'),
            'client_id' => Client::factory(),
            'matter_type_id' => null,
            'responsible_user_id' => User::factory(),
            'opened_by_user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'status' => 'open',
            'priority' => 'normal',
            'billing_type' => 'hourly',
            'default_hourly_rate' => fake()->randomFloat(2, 10000, 100000),
            'budget_amount' => fake()->randomFloat(2, 100000, 1000000),
            'opened_at' => today(),
        ];
    }
}
