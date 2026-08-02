<?php

namespace Database\Factories;

use App\Models\CompanyDriveFolder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyDriveFolder>
 */
class CompanyDriveFolderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'client_id' => null,
            'created_by_user_id' => null,
            'name' => fake()->unique()->words(2, true),
            'library' => CompanyDriveFolder::LibraryGeneral,
            'is_system' => false,
            'metadata' => null,
        ];
    }
}
