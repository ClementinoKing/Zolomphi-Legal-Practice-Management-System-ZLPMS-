<?php

use App\Models\Department;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AdministrationSeeder;
use Illuminate\Support\Facades\Gate;

test('the practice role templates are seeded with database-backed permissions', function () {
    $this->seed(AdministrationSeeder::class);

    expect(Role::query()->pluck('name')->all())
        ->toContain(
            'Super Admin',
            'Firm Administrator',
            'Managing Partner',
            'Department Head',
            'Attorney',
            'Paralegal & Clerk',
            'Finance Manager',
            'Finance Officer',
            'Client Services',
            'ICT Support',
            'Security Auditor',
        );
    expect(Role::query()->where('name', 'Admin')->exists())->toBeFalse();
    expect(Permission::query()->where('key', 'matters.view.assigned')->exists())->toBeTrue();
    expect(Permission::query()->where('key', 'documents.view.private')->exists())->toBeTrue();
    expect(Permission::query()->where('key', 'payments.record')->exists())->toBeTrue();

    $attorney = User::factory()->create();
    $attorney->roles()->attach(Role::query()->where('name', 'Attorney')->sole());

    expect(Gate::forUser($attorney)->allows('matters.view.assigned'))->toBeTrue();
    expect(Gate::forUser($attorney)->allows('payments.record'))->toBeFalse();

    $ictSupport = User::factory()->create();
    $ictSupport->roles()->attach(Role::query()->where('name', 'ICT Support')->sole());

    expect(Gate::forUser($ictSupport)->allows('payments.record'))->toBeTrue();
    expect(Gate::forUser($ictSupport)->allows('documents.view.private'))->toBeTrue();
});

test('the legacy admin role is migrated to firm administrator without losing user access', function () {
    $legacyAdmin = Role::query()->create(['name' => 'Admin']);
    $user = User::factory()->create();
    $user->roles()->attach($legacyAdmin);

    $this->seed(AdministrationSeeder::class);

    expect(Role::query()->where('name', 'Admin')->exists())->toBeFalse();
    expect($user->refresh()->roles()->pluck('name')->all())->toContain('Firm Administrator');
});

test('unassigned staff receive a least-privilege role from their position', function () {
    $department = Department::query()->where('code', 'litigation-dispute-resolution')->firstOrFail();
    $position = Position::query()->where('code', 'senior-associate-litigation')->firstOrFail();
    $user = User::factory()->create([
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);

    $this->seed(AdministrationSeeder::class);

    expect($user->refresh()->roles()->pluck('name')->all())->toBe(['Attorney'])
        ->and($user->hasPermission('dashboard.view'))->toBeTrue()
        ->and($user->hasPermission('matters.view.assigned'))->toBeTrue()
        ->and($user->hasPermission('payments.record'))->toBeFalse();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});
