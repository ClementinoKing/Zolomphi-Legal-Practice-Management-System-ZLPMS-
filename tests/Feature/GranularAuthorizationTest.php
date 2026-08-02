<?php

use App\Models\Document;
use App\Models\Matter;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

function userWithPermissions(array $permissions): User
{
    $role = Role::query()->create(['name' => fake()->unique()->word()]);
    $permissionModels = collect($permissions)->map(fn (string $key): Permission => Permission::query()->firstOrCreate(
        ['key' => $key],
        ['name' => $key, 'group' => 'Test'],
    ));
    $role->assignedPermissions()->sync($permissionModels->pluck('id')->all());

    $user = User::factory()->create();
    $user->roles()->attach($role);

    return $user;
}

test('module routes require their catalog permission instead of administration access', function () {
    $administratorOnly = userWithPermissions(['administration.access']);

    $this->actingAs($administratorOnly)
        ->get(route('clients.index'))
        ->assertForbidden();

    $this->actingAs(userWithPermissions(['clients.view']))
        ->get(route('clients.index'))
        ->assertOk();
});

test('assigned matter users can only see their own matter records', function () {
    $user = userWithPermissions(['matters.view.assigned']);
    $assignedMatter = Matter::factory()->create(['responsible_user_id' => $user->id]);
    $otherMatter = Matter::factory()->create();

    expect(Gate::forUser($user)->allows('view', $assignedMatter))->toBeTrue()
        ->and(Gate::forUser($user)->allows('view', $otherMatter))->toBeFalse();

    $this->actingAs($user)
        ->get(route('matters.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('matters', fn (Collection $matters): bool => $matters->pluck('id')->all() === [$assignedMatter->id]));
});

test('private matter documents need the private-document permission', function () {
    $user = userWithPermissions(['matters.view.assigned', 'documents.view']);
    $matter = Matter::factory()->create(['responsible_user_id' => $user->id]);
    $document = Document::query()->create([
        'matter_id' => $matter->id,
        'client_id' => $matter->client_id,
        'uploaded_by_user_id' => $user->id,
        'name' => 'Internal advice',
        'category' => 'legal',
        'visibility' => 'internal',
        'disk' => 'local',
        'path' => 'documents/internal-advice.pdf',
        'original_filename' => 'internal-advice.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 100,
    ]);

    $privateUser = userWithPermissions([
        'matters.view.assigned',
        'documents.view',
        'documents.view.private',
    ]);
    $matter->attorneys()->attach($privateUser);

    expect(Gate::forUser($user)->allows('view', $document))->toBeFalse()
        ->and(Gate::forUser($privateUser)->allows('view', $document))->toBeTrue();
});

test('roles can be managed without granting access to unrelated modules', function () {
    $user = userWithPermissions(['roles.manage']);

    $this->actingAs($user)
        ->get(route('administration.roles.index'))
        ->assertOk();

    $this->get(route('invoices.index'))->assertForbidden();
});

test('user deletion requires its dedicated permission', function () {
    $target = User::factory()->create();

    $this->actingAs(userWithPermissions(['users.manage']))
        ->delete(route('administration.users.destroy', $target))
        ->assertForbidden();

    $this->actingAs(userWithPermissions(['users.delete']))
        ->delete(route('administration.users.destroy', $target))
        ->assertRedirect(route('administration.users.index', absolute: false));

    expect(User::query()->find($target->id))->toBeNull();
});

test('the last super admin account cannot be deleted', function () {
    $superAdminRole = Role::query()->create(['name' => 'Super Admin']);
    $target = User::factory()->create();
    $target->roles()->attach($superAdminRole);
    $deleter = userWithPermissions(['users.delete']);

    $this->actingAs($deleter)
        ->delete(route('administration.users.destroy', $target))
        ->assertSessionHasErrors('user');

    expect(User::query()->find($target->id))->not->toBeNull();
});
