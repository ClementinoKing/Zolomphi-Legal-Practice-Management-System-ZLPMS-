<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('users with a temporary password must create a new password before accessing the workspace', function () {
    $user = User::factory()->create(['must_change_password' => true]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('password.change'));

    $this->actingAs($user)
        ->get(route('password.change'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/force-password-change')
            ->has('passwordRules'));
});

test('a user can replace a temporary password and access the workspace', function () {
    $user = User::factory()->create(['must_change_password' => true]);

    $this->actingAs($user)
        ->put(route('password.change.update'), [
            'password' => 'A-stronger-password1!',
            'password_confirmation' => 'A-stronger-password1!',
        ])
        ->assertRedirect(route('dashboard'));

    $user->refresh();

    expect($user->must_change_password)->toBeFalse();
    expect($user->password_changed_at)->not->toBeNull();
    expect(Hash::check('A-stronger-password1!', $user->password))->toBeTrue();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});
