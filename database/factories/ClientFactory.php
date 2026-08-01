<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_number' => fake()->unique()->numerify('CLI-######'),
            'type' => 'organization',
            'full_name' => null,
            'organization_name' => fake()->unique()->company(),
            'contact_person' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'alternate_phone' => fake()->optional()->phoneNumber(),
            'tax_number' => fake()->optional()->numerify('TAX-######'),
            'registration_number' => fake()->optional()->numerify('REG-######'),
            'address_line1' => fake()->streetAddress(),
            'address_line2' => null,
            'city' => fake()->city(),
            'state' => null,
            'postal_code' => null,
            'country' => 'Malawi',
            'notes' => fake()->optional()->sentence(),
            'metadata' => null,
            'status' => 'active',
        ];
    }
}
