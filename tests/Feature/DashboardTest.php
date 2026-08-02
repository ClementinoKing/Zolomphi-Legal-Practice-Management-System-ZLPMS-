<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

function dashboardAdministrator(): User
{
    $permission = Permission::query()->create([
        'key' => 'administration.access',
        'name' => 'Access administration',
        'group' => 'Administration',
    ]);
    $role = Role::query()->create(['name' => 'Dashboard administrator']);
    $role->assignedPermissions()->attach($permission);

    $user = User::factory()->create();
    $user->roles()->attach($role);

    return $user;
}

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = dashboardAdministrator();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});
