<?php

namespace Database\Factories;

use App\Models\Matter;
use App\Models\TimeTracker;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeTracker>
 */
class TimeTrackerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'matter_id' => Matter::factory(),
            'status' => 'running',
            'started_at' => now(),
            'last_resumed_at' => now(),
            'accumulated_seconds' => 0,
        ];
    }

    public function paused(int $seconds = 900): static
    {
        return $this->state(fn (): array => [
            'status' => 'paused',
            'last_resumed_at' => null,
            'paused_at' => now(),
            'accumulated_seconds' => $seconds,
        ]);
    }

    public function stopped(int $seconds = 900): static
    {
        return $this->state(fn (): array => [
            'status' => 'stopped',
            'last_resumed_at' => null,
            'stopped_at' => now(),
            'accumulated_seconds' => $seconds,
        ]);
    }
}
