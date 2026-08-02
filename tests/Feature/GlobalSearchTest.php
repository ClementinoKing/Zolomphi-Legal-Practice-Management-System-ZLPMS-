<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Matter;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function globalSearchAdministrator(): User
{
    $permission = Permission::query()->create([
        'key' => 'administration.access',
        'name' => 'Access administration',
        'group' => 'Administration',
    ]);
    $role = Role::query()->create(['name' => 'Search administrator']);
    $role->assignedPermissions()->attach($permission);

    $user = User::factory()->create();
    $user->roles()->attach($role);

    return grantAllPracticePermissions($user);
}

test('the global search returns matching practice records', function () {
    $administrator = globalSearchAdministrator();
    $client = Client::factory()->create([
        'organization_name' => 'Zolomphi Search Holdings',
        'client_number' => 'CLI-SEARCH',
    ]);
    Matter::factory()->create([
        'client_id' => $client->id,
        'title' => 'Zolomphi Search litigation review',
        'matter_number' => 'MAT-SEARCH',
    ]);
    Invoice::factory()->create([
        'client_id' => $client->id,
        'invoice_number' => 'INV-SEARCH',
    ]);
    User::factory()->create(['name' => 'Zolomphi Search User']);

    $this->actingAs($administrator)
        ->get(route('search.index', ['query' => 'SEARCH']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('search/index')
            ->where('query', 'SEARCH')
            ->has('results.clients', 1)
            ->has('results.matters', 1)
            ->has('results.invoices', 1)
            ->has('results.users', 1));

    $this->actingAs($administrator)
        ->getJson(route('search.suggestions', ['query' => 'SEARCH']))
        ->assertOk()
        ->assertJsonCount(1, 'results.clients')
        ->assertJsonPath('results.clients.0.title', 'Zolomphi Search Holdings')
        ->assertJsonCount(1, 'results.matters');
});

test('the search and help pages require authenticated administration access', function () {
    $this->get(route('search.index'))->assertRedirect(route('login'));
    $this->get(route('help.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('search.index'))
        ->assertForbidden();

    $this->actingAs(globalSearchAdministrator())
        ->get(route('help.index'))
        ->assertInertia(fn (Assert $page) => $page->component('help/index'));
});
