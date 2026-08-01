<?php

use App\Models\BillingItem;
use App\Models\Client;
use App\Models\Department;
use App\Models\GeneralSetting;
use App\Models\MatterType;
use App\Models\Matter;
use App\Models\MatterParty;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AdministrationSeeder;
use Inertia\Testing\AssertableInertia as Assert;

function administrationUser(): User
{
    $permission = Permission::query()->create([
        'key' => 'administration.access',
        'name' => 'Access administration',
        'group' => 'Administration',
    ]);
    $role = Role::query()->create(['name' => 'Super Admin']);
    $role->assignedPermissions()->attach($permission);

    $user = User::factory()->create();
    $user->roles()->attach($role);

    return $user;
}

test('the administration entry and legacy catalog redirect to focused pages', function () {
    $this->actingAs(administrationUser())
        ->get(route('administration.index'))
        ->assertRedirect(route('administration.firm'));

    $this->get(route('administration.catalog'))
        ->assertRedirect(route('administration.matter-types.index'));
});

test('guests are redirected and users without administration access are forbidden', function () {
    $administrationPages = [
        route('administration.firm'),
        route('administration.users.index'),
        route('administration.departments.index'),
        route('administration.positions.index'),
        route('administration.access'),
        route('administration.roles.index'),
        route('administration.permissions.index'),
        route('administration.catalog'),
        route('administration.matter-types.index'),
        route('administration.billing-items.index'),
        route('administration.expense-categories.index'),
        route('administration.payment-methods.index'),
        route('clients.index'),
        route('matters.index'),
        route('matters.open'),
    ];

    foreach ($administrationPages as $administrationPage) {
        $this->get($administrationPage)->assertRedirect(route('login'));
    }

    foreach ($administrationPages as $administrationPage) {
        $this->actingAs(User::factory()->create())
            ->get($administrationPage)
            ->assertForbidden();
    }
});

test('the administration seeder grants the initial firm user super admin access', function () {
    $initialUser = User::factory()->create(['email' => 'paliponsemw@gmail.com']);

    $this->seed(AdministrationSeeder::class);

    expect(Permission::query()->count())->toBe(10);
    expect($initialUser->refresh()->roles()->pluck('name')->all())->toContain('Super Admin');
    expect($initialUser->hasPermission('administration.access'))->toBeTrue();
});

test('law-firm departments and positions are available from the database migration', function () {
    expect(Department::query()->where('is_active', true)->count())->toBe(7);
    expect(Position::query()->where('is_active', true)->count())->toBe(23);
    expect(Position::query()
        ->where('code', 'managing-partner')
        ->firstOrFail()
        ->department()
        ->value('code'))
        ->toBe('management-partnerships');
    expect(Position::query()
        ->where('code', 'ict-manager')
        ->firstOrFail()
        ->department()
        ->value('code'))
        ->toBe('ict');
});

test('administrators can save firm settings and create access records', function () {
    $administrator = administrationUser();
    $this->actingAs($administrator);

    $this->put(route('administration.firm-settings.update'), [
        'firm_name' => 'Zolomphi & Co.',
        'email' => 'office@zolomphi.test',
        'phone' => '+265 999 000 000',
        'website' => 'https://zolomphi.test',
        'timezone' => 'Africa/Blantyre',
        'currency' => 'MWK',
    ])->assertRedirect(route('administration.firm', absolute: false));

    expect(GeneralSetting::query()->firstOrFail()->firm_name)->toBe('Zolomphi & Co.');

    $permission = Permission::query()->create([
        'key' => 'matters.manage',
        'name' => 'Manage matters',
        'group' => 'Matters',
    ]);

    $this->post(route('administration.roles.store'), [
        'name' => 'Matter Manager',
        'description' => 'Can manage legal matters.',
        'permission_ids' => [$permission->id],
    ])->assertRedirect(route('administration.roles.index', absolute: false));

    $role = Role::query()->where('name', 'Matter Manager')->firstOrFail();
    expect($role->assignedPermissions()->pluck('key')->all())->toBe(['matters.manage']);

    $department = Department::factory()->create(['name' => 'Legal Services']);
    $position = Position::factory()->create([
        'department_id' => $department->id,
        'name' => 'Legal Associate',
        'code' => 'legal-associate',
    ]);

    $this->post(route('administration.users.store'), [
        'name' => 'Practice User',
        'email' => 'practice.user@example.test',
        'status' => 'active',
        'department_id' => $department->id,
        'position_id' => $position->id,
        'role_ids' => [$role->id],
    ])->assertRedirect(route('administration.users.index', absolute: false));

    $user = User::query()->where('email', 'practice.user@example.test')->firstOrFail();
    expect($user->roles()->pluck('name')->all())->toBe(['Matter Manager']);
    expect($user->department_id)->toBe($department->id);
    expect($user->position_id)->toBe($position->id);

    $this->patch(route('administration.users.update', $user), [
        'name' => 'Updated Practice User',
        'email' => 'practice.user@example.test',
        'status' => 'inactive',
        'department_id' => $department->id,
        'position_id' => $position->id,
        'role_ids' => [$role->id],
    ])->assertRedirect(route('administration.users.index', absolute: false));

    expect($user->refresh()->name)->toBe('Updated Practice User');
    expect($user->status)->toBe('inactive');
});

test('a user position must belong to the selected department', function () {
    $this->actingAs(administrationUser());
    $role = Role::query()->create(['name' => 'Legal Staff']);
    $legal = Department::factory()->create(['name' => 'Legal Services']);
    $finance = Department::factory()->create(['name' => 'Finance Services']);
    $position = Position::factory()->create([
        'department_id' => $finance->id,
        'name' => 'Finance Officer',
        'code' => 'finance-officer',
    ]);

    $this->post(route('administration.users.store'), [
        'name' => 'Position Mismatch',
        'email' => 'position.mismatch@example.test',
        'status' => 'active',
        'department_id' => $legal->id,
        'position_id' => $position->id,
        'role_ids' => [$role->id],
    ])->assertSessionHasErrors('position_id');
});

test('the users page includes saved department and position identifiers', function () {
    $administrator = administrationUser();
    $administrator->update(['name' => 'Administrator']);
    $department = Department::query()->where('code', 'ict')->firstOrFail();
    $position = Position::query()->where('code', 'ict-manager')->firstOrFail();

    User::factory()->create([
        'name' => 'Positioned Team Member',
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);

    $this->actingAs($administrator)
        ->get(route('administration.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('administration/users')
            ->where('users.1.department_id', $department->id)
            ->where('users.1.position_id', $position->id),
        );
});

test('administrators can create and update departments', function () {
    $this->actingAs(administrationUser());

    $this->post(route('administration.departments.store'), [
        'name' => 'Litigation',
        'code' => 'litigation',
        'description' => 'Court and dispute resolution work.',
        'is_active' => true,
    ])->assertRedirect(route('administration.departments.index', absolute: false));

    $department = Department::query()->where('code', 'litigation')->firstOrFail();
    expect($department->name)->toBe('Litigation');

    $this->patch(route('administration.departments.update', $department), [
        'name' => 'Dispute Resolution',
        'code' => 'dispute-resolution',
        'description' => 'Court and dispute resolution work.',
        'is_active' => false,
    ])->assertRedirect(route('administration.departments.index', absolute: false));

    expect($department->refresh()->is_active)->toBeFalse();
    expect($department->name)->toBe('Dispute Resolution');
});

test('administrators can manage positions within departments', function () {
    $this->actingAs(administrationUser());
    $department = Department::factory()->create(['name' => 'Litigation']);

    $this->post(route('administration.positions.store'), [
        'department_id' => $department->id,
        'name' => 'Senior Associate',
        'description' => 'Leads complex litigation files.',
        'is_active' => true,
    ])->assertRedirect(route('administration.positions.index', absolute: false));

    $position = Position::query()->where('code', 'senior-associate')->firstOrFail();
    expect($position->department_id)->toBe($department->id);

    $this->patch(route('administration.positions.update', $position), [
        'department_id' => $department->id,
        'name' => 'Principal Associate',
        'description' => 'Leads complex litigation files.',
        'is_active' => false,
    ])->assertRedirect(route('administration.positions.index', absolute: false));

    expect($position->refresh()->code)->toBe('principal-associate');
    expect($position->is_active)->toBeFalse();
});

test('administrators can create and update clients', function () {
    $this->actingAs(administrationUser());

    $this->post(route('clients.store'), [
        'type' => 'organization',
        'name' => 'Chisomo Holdings Limited',
        'contact_person' => 'Annie Chisomo',
        'email' => 'legal@chisomo.example.test',
        'phone' => '+265 999 000 001',
        'address_line1' => 'Area 3',
        'city' => 'Lilongwe',
        'country' => 'Malawi',
        'notes' => 'Corporate client.',
        'status' => 'active',
    ])->assertRedirect(route('clients.index', absolute: false));

    $client = Client::query()->where('email', 'legal@chisomo.example.test')->firstOrFail();
    expect($client->client_number)->toBe('CLI-000001');
    expect($client->contact_person)->toBe('Annie Chisomo');

    $this->patch(route('clients.update', $client), [
        'type' => 'organization',
        'name' => 'Chisomo Holdings Plc',
        'contact_person' => 'Annie Chisomo',
        'email' => 'legal@chisomo.example.test',
        'phone' => '+265 999 000 001',
        'address_line1' => 'Area 3',
        'city' => 'Lilongwe',
        'country' => 'Malawi',
        'notes' => 'Corporate client.',
        'status' => 'inactive',
    ])->assertRedirect(route('clients.index', absolute: false));

    expect($client->refresh()->client_number)->toBe('CLI-000001');
    expect($client->status)->toBe('inactive');
});

test('the clients page is available to administrators', function () {
    $this->actingAs(administrationUser())
        ->get(route('clients.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('clients/index'));
});

test('administrators can open a matter with participants', function () {
    $administrator = administrationUser();
    $client = Client::factory()->create();
    $responsibleAttorney = User::factory()->create(['status' => 'active']);
    $matterType = MatterType::query()->create([
        'name' => 'Commercial litigation',
        'code' => 'commercial-litigation',
        'default_billing_type' => 'hourly',
        'default_hourly_rate' => 75000,
        'is_active' => true,
    ]);

    $this->actingAs($administrator)
        ->post(route('matters.store'), [
            'client_id' => $client->id,
            'matter_type_id' => $matterType->id,
            'responsible_user_id' => $responsibleAttorney->id,
            'title' => 'Chisomo Holdings contract dispute',
            'description' => 'Review and pursue the supplier contract dispute.',
            'status' => 'open',
            'priority' => 'high',
            'billing_type' => 'hourly',
            'default_hourly_rate' => 75000,
            'budget_amount' => 2500000,
            'opened_at' => '2026-08-01',
            'participants' => [[
                'role' => 'Opposing party',
                'type' => 'organization',
                'name' => 'Mphatso Suppliers Limited',
                'contact_person' => 'Grace Mphatso',
                'email' => 'legal@mphatso.example.test',
                'phone' => '+265 999 000 002',
            ]],
        ])
        ->assertRedirect(route('matters.index', absolute: false));

    $matter = Matter::query()->firstOrFail();
    expect($matter->matter_number)->toBe('MAT-000001');
    expect($matter->client_id)->toBe($client->id);
    expect($matter->matter_type_id)->toBe($matterType->id);
    expect($matter->responsible_user_id)->toBe($responsibleAttorney->id);
    expect($matter->budget_amount)->toBe('2500000.00');
    expect(MatterParty::query()->where('matter_id', $matter->id)->value('name'))
        ->toBe('Mphatso Suppliers Limited');
});

test('the matters page includes its table and opening reference data', function () {
    $administrator = administrationUser();
    $client = Client::factory()->create();
    $matterType = MatterType::query()->create([
        'name' => 'Property conveyancing',
        'code' => 'property-conveyancing',
        'default_billing_type' => 'fixed_fee',
        'is_active' => true,
    ]);

    $this->actingAs($administrator)
        ->get(route('matters.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('matters/index')
            ->where('clients.0.id', $client->id)
            ->where('matterTypes.0.id', $matterType->id));

    $this->get(route('matters.open'))
        ->assertRedirect(route('matters.index'));
});

test('administrators can create matter and billing configuration items', function () {
    $this->actingAs(administrationUser());

    $this->post(route('administration.reference-data.store', ['resource' => 'matter-types']), [
        'name' => 'Litigation',
        'description' => 'Court-related matters.',
        'default_billing_type' => 'hourly',
        'default_hourly_rate' => 50000,
        'is_active' => true,
        'sort_order' => 1,
    ])->assertRedirect(route('administration.matter-types.index', absolute: false));

    $this->post(route('administration.reference-data.store', ['resource' => 'billing-items']), [
        'name' => 'Consultation',
        'unit' => 'hour',
        'default_amount' => 50000,
        'tax_rate' => 16.5,
        'is_active' => true,
        'sort_order' => 1,
    ])->assertRedirect(route('administration.billing-items.index', absolute: false));

    expect(MatterType::query()->where('code', 'litigation')->exists())->toBeTrue();
    expect(BillingItem::query()->where('code', 'consultation')->exists())->toBeTrue();
});

test('reference-data codes are generated from names and remain unique', function () {
    $this->actingAs(administrationUser());

    foreach (['Family & Estate', 'Family Estate'] as $name) {
        $this->post(route('administration.reference-data.store', ['resource' => 'matter-types']), [
            'name' => $name,
            'code' => 'manual-code',
            'default_billing_type' => 'hourly',
            'is_active' => true,
            'sort_order' => 0,
        ])->assertRedirect(route('administration.matter-types.index', absolute: false));
    }

    expect(MatterType::query()->orderBy('code')->pluck('code')->all())
        ->toBe(['family-estate', 'family-estate-2']);
});

test('administrators can visit the separate reference-data pages', function () {
    $this->actingAs(administrationUser());

    foreach ([
        'administration/matter-types' => route('administration.matter-types.index'),
        'administration/billing-items' => route('administration.billing-items.index'),
        'administration/expense-categories' => route('administration.expense-categories.index'),
        'administration/payment-methods' => route('administration.payment-methods.index'),
        'administration/positions' => route('administration.positions.index'),
    ] as $component => $page) {
        $this->get($page)
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response->component($component));
    }
});
