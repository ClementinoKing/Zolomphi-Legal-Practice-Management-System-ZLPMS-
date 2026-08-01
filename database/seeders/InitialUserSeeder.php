<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use LogicException;

class InitialUserSeeder extends Seeder
{
    private const string InitialUserEmail = 'paliponsemw@gmail.com';

    /**
     * Create the firm's initial sign-in account when it does not already exist.
     */
    public function run(): void
    {
        $password = config('initial-user.password');

        if (! is_string($password) || blank($password)) {
            throw new LogicException('Set INITIAL_USER_PASSWORD before running the initial user seeder.');
        }

        $user = User::query()->firstOrCreate(
            ['email' => self::InitialUserEmail],
            [
                'name' => 'Paliponse Technologies',
                'password' => Hash::make($password),
            ],
        );

        if ($user->wasRecentlyCreated) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }
    }
}
