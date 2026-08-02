<?php

use App\Models\Permission;
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
