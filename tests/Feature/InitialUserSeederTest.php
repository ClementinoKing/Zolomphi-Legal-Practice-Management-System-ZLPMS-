<?php

use App\Models\User;
use Database\Seeders\InitialUserSeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;

test('creates the initial firm user once', function () {
    Config::set('initial-user.password', 'password');

    $this->seed(InitialUserSeeder::class);

    $user = User::query()
        ->where('email', 'paliponsemw@gmail.com')
        ->firstOrFail();

    $this->assertModelExists($user);
    expect($user->name)->toBe('Paliponse Technologies');
    expect($user->email_verified_at)->not->toBeNull();
    expect(Hash::check('password', $user->password))->toBeTrue();

    $this->seed(InitialUserSeeder::class);

    expect(User::query()->where('email', $user->email)->count())->toBe(1);
});
