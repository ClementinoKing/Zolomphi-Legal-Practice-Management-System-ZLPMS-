<?php

use App\Actions\CompanyDrive\EnsureClientDriveFolder;
use App\Mail\MatterEmailMessage;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\BillingItem;
use App\Models\Client;
use App\Models\Department;
use App\Models\Document;
use App\Models\ExpenseCategory;
use App\Models\GeneralSetting;
use App\Models\Matter;
use App\Models\MatterParty;
use App\Models\MatterType;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Support\PracticeAccessCatalog;
use Database\Seeders\AdministrationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function administrationUser(): User
{
    $accessPermission = Permission::query()->firstOrCreate(['key' => 'administration.access'], [
        'name' => 'Access administration',
        'group' => 'Administration',
    ]);
    $rolePermission = Permission::query()->firstOrCreate(['key' => 'roles.manage'], [
        'name' => 'Manage roles',
        'group' => 'Access control',
    ]);
    $role = Role::query()->firstOrCreate(['name' => 'Super Admin']);
    $role->assignedPermissions()->syncWithoutDetaching([$accessPermission->id, $rolePermission->id]);

    $user = User::factory()->create();
    $user->roles()->attach($role);

    return grantAllPracticePermissions($user);
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
        route('calendar.index'),
        route('files.index'),
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

    expect(Permission::query()->count())->toBe(count(PracticeAccessCatalog::permissions()));
    expect($initialUser->refresh()->roles()->pluck('name')->all())->toContain('Super Admin');
    expect($initialUser->hasPermission('administration.access'))->toBeTrue();
    expect($initialUser->hasPermission('users.delete'))->toBeTrue();
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

test('the database includes non-ICT staff assigned to their configured departments and positions', function () {
    $staff = User::query()
        ->where('email', 'like', '%@zolomphi.com')
        ->with(['assignedDepartment:id,code', 'assignedPosition:id,department_id'])
        ->get();

    expect($staff)->toHaveCount(25);
    expect(Hash::check('12345678', $staff->firstWhere('email', 'mbanda@zolomphi.com')->password))->toBeTrue();

    $staff->each(function (User $staffMember): void {
        expect($staffMember->assignedDepartment->code)->not->toBe('ict');
        expect($staffMember->assignedPosition->department_id)->toBe($staffMember->department_id);
        expect($staffMember->status)->toBe('active');
        expect($staffMember->email_verified_at)->not->toBeNull();
    });
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
        'password' => '12345678',
        'password_confirmation' => '12345678',
        'status' => 'active',
        'department_id' => $department->id,
        'position_id' => $position->id,
        'role_ids' => [$role->id],
    ])->assertRedirect(route('administration.users.index', absolute: false));

    $user = User::query()->where('email', 'practice.user@example.test')->firstOrFail();
    expect($user->roles()->pluck('name')->all())->toBe(['Matter Manager']);
    expect($user->department_id)->toBe($department->id);
    expect($user->position_id)->toBe($position->id);
    expect(Hash::check('12345678', $user->password))->toBeTrue();
    expect($user->must_change_password)->toBeTrue();

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

test('only users with the role-management permission can edit role permissions', function () {
    $accessPermission = Permission::query()->create([
        'key' => 'administration.access',
        'name' => 'Access administration',
        'group' => 'Administration',
    ]);
    $viewerRole = Role::query()->create(['name' => 'Firm Administrator']);
    $viewerRole->assignedPermissions()->attach($accessPermission);
    $viewer = User::factory()->create();
    $viewer->roles()->attach($viewerRole);

    $targetRole = Role::query()->create(['name' => 'Attorney']);
    $clientPermission = Permission::query()->create([
        'key' => 'clients.view',
        'name' => 'View clients',
        'group' => 'Clients',
    ]);

    $this->actingAs($viewer)
        ->patch(route('administration.roles.update', $targetRole), [
            'name' => 'Attorney',
            'permission_ids' => [$clientPermission->id],
        ])
        ->assertForbidden();

    $this->actingAs(administrationUser())
        ->patch(route('administration.roles.update', $targetRole), [
            'name' => 'Attorney',
            'permission_ids' => [$clientPermission->id],
        ])
        ->assertRedirect(route('administration.roles.index', absolute: false));

    expect($targetRole->refresh()->assignedPermissions()->pluck('key')->all())
        ->toBe(['clients.view']);
});

test('administrators can choose a supported firm display currency', function () {
    $this->actingAs(administrationUser())
        ->put(route('administration.firm-settings.update'), [
            'firm_name' => 'Zolomphi & Co.',
            'timezone' => 'Africa/Blantyre',
            'currency' => 'USD',
        ])
        ->assertRedirect(route('administration.firm', absolute: false));

    expect(GeneralSetting::query()->firstOrFail()->currency)->toBe('USD');

    $this->put(route('administration.firm-settings.update'), [
        'firm_name' => 'Zolomphi & Co.',
        'timezone' => 'Africa/Blantyre',
        'currency' => 'AUD',
    ])->assertSessionHasErrors('currency');
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
        'password' => '12345678',
        'password_confirmation' => '12345678',
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

    $positionedUser = User::factory()->create([
        'name' => 'Positioned Team Member',
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);

    $this->actingAs($administrator)
        ->get(route('administration.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('administration/users')
            ->where(
                'users.'.User::query()->orderBy('name')->pluck('id')->search($positionedUser->id).'.department_id',
                $department->id,
            )
            ->where(
                'users.'.User::query()->orderBy('name')->pluck('id')->search($positionedUser->id).'.position_id',
                $position->id,
            ),
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
        'is_attorney' => true,
    ])->assertRedirect(route('administration.positions.index', absolute: false));

    $position = Position::query()->where('code', 'senior-associate')->firstOrFail();
    expect($position->department_id)->toBe($department->id);

    $this->patch(route('administration.positions.update', $position), [
        'department_id' => $department->id,
        'name' => 'Principal Associate',
        'description' => 'Leads complex litigation files.',
        'is_active' => false,
        'is_attorney' => true,
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
    expect($client->client_number)->toMatch('/^CLI-\\d{6}$/');
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

    expect($client->refresh()->client_number)->toMatch('/^CLI-\\d{6}$/');
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
    $legalDepartment = Department::factory()->create();
    $attorneyPosition = Position::factory()->create([
        'department_id' => $legalDepartment->id,
        'is_attorney' => true,
    ]);
    $responsibleAttorney = User::factory()->create([
        'status' => 'active',
        'department_id' => $legalDepartment->id,
        'position_id' => $attorneyPosition->id,
    ]);
    $participatingAttorney = User::factory()->create([
        'status' => 'active',
        'department_id' => $legalDepartment->id,
        'position_id' => $attorneyPosition->id,
    ]);
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
            'deadline_at' => '2026-08-30',
            'attorney_ids' => [$participatingAttorney->id],
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

    $matter = Matter::query()->where('title', 'Chisomo Holdings contract dispute')->firstOrFail();
    expect($matter->matter_number)->toMatch('/^MAT-\\d{6}$/');
    expect($matter->client_id)->toBe($client->id);
    expect($matter->matter_type_id)->toBe($matterType->id);
    expect($matter->responsible_user_id)->toBe($responsibleAttorney->id);
    expect($matter->budget_amount)->toBe('2500000.00');
    expect($matter->deadline_at?->toDateString())->toBe('2026-08-30');
    expect($matter->attorneys()->pluck('users.id')->sort()->values()->all())
        ->toBe(collect([$responsibleAttorney->id, $participatingAttorney->id])->sort()->values()->all());
    expect(MatterParty::query()->where('matter_id', $matter->id)->value('name'))
        ->toBe('Mphatso Suppliers Limited');
});

test('matter attorney assignments reject users without attorney positions', function () {
    $administrator = administrationUser();
    $client = Client::factory()->create();
    $department = Department::factory()->create();
    $attorneyPosition = Position::factory()->create([
        'department_id' => $department->id,
        'is_attorney' => true,
    ]);
    $supportPosition = Position::factory()->create([
        'department_id' => $department->id,
        'is_attorney' => false,
    ]);
    $responsibleAttorney = User::factory()->create([
        'status' => 'active',
        'department_id' => $department->id,
        'position_id' => $attorneyPosition->id,
    ]);
    $supportUser = User::factory()->create([
        'status' => 'active',
        'department_id' => $department->id,
        'position_id' => $supportPosition->id,
    ]);
    $matterType = MatterType::query()->create([
        'name' => 'Attorney validation',
        'code' => 'attorney-validation',
        'default_billing_type' => 'hourly',
        'is_active' => true,
    ]);

    $this->actingAs($administrator)
        ->post(route('matters.store'), [
            'client_id' => $client->id,
            'matter_type_id' => $matterType->id,
            'responsible_user_id' => $responsibleAttorney->id,
            'title' => 'Attorney participant validation',
            'status' => 'open',
            'priority' => 'normal',
            'billing_type' => 'hourly',
            'default_hourly_rate' => 50000,
            'opened_at' => '2026-08-01',
            'attorney_ids' => [$supportUser->id],
        ])
        ->assertSessionHasErrors('attorney_ids.0');
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

    $clientIndex = Client::query()
        ->where('status', 'active')
        ->get(['id', 'full_name', 'organization_name'])
        ->map(fn (Client $availableClient): array => [
            'id' => $availableClient->id,
            'name' => $availableClient->full_name ?? $availableClient->organization_name ?? 'Unnamed client',
        ])
        ->sortBy('name')
        ->pluck('id')
        ->search($client->id);
    $matterTypeIndex = MatterType::query()
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->orderBy('name')
        ->pluck('id')
        ->search($matterType->id);

    $this->actingAs($administrator)
        ->get(route('matters.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('matters/index')
            ->where('clients.'.$clientIndex.'.id', $client->id)
            ->where('matterTypes.'.$matterTypeIndex.'.id', $matterType->id));

    $this->get(route('matters.open'))
        ->assertRedirect(route('matters.index'));
});

test('administrators can perform and audit legal work in a matter workspace', function () {
    Storage::fake('local');
    Mail::fake();
    $administrator = administrationUser();
    $client = Client::factory()->create();
    $matter = Matter::factory()->create([
        'client_id' => $client->id,
        'responsible_user_id' => $administrator->id,
        'opened_by_user_id' => $administrator->id,
        'default_hourly_rate' => 60000,
    ]);
    $expenseCategory = ExpenseCategory::query()->create([
        'name' => 'Court filing fees',
        'code' => 'court-filing-fees',
        'is_active' => true,
    ]);
    $billingItem = BillingItem::query()->create([
        'name' => 'Fixed consultation',
        'code' => 'fixed-consultation',
        'unit' => 'item',
        'default_amount' => 250000,
        'tax_rate' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($administrator)
        ->get(route('matters.show', $matter))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('matters/show')
            ->where('matter.id', $matter->id)
            ->where('summary.open_tasks', 0));

    $this->post(route('matters.workspace.store', [$matter, 'tasks']), [
        'title' => 'Prepare witness statement', 'priority' => 'high', 'due_at' => '2026-08-20', 'assignee_ids' => [$administrator->id],
    ])->assertRedirect(route('matters.show', $matter, absolute: false));
    $task = $matter->tasks()->latest('id')->firstOrFail();
    expect($task->assignees()->pluck('id')->all())->toBe([$administrator->id]);

    $this->patch(route('matters.workspace.update', [$matter, 'tasks', $task]), [
        'title' => 'Prepare amended witness statement', 'priority' => 'urgent', 'assignee_ids' => [$administrator->id],
    ])->assertRedirect(route('matters.show', $matter, absolute: false));
    expect($task->refresh()->title)->toBe('Prepare amended witness statement');

    $this->post(route('matters.workspace.store', [$matter, 'time-entries']), [
        'entry_date' => '2026-08-10', 'description' => 'Reviewed the file.', 'hours' => 1.25, 'is_billable' => true, 'status' => 'draft',
    ])->assertRedirect(route('matters.show', $matter, absolute: false));
    $timeEntry = $matter->timeEntries()->latest('id')->firstOrFail();
    expect($timeEntry->hourly_rate)->toBe('60000.00');
    $this->patch(route('matters.workspace.update', [$matter, 'time-entries', $timeEntry]), [
        'entry_date' => '2026-08-11', 'description' => 'Reviewed amended instructions.', 'hours' => 1.5, 'hourly_rate' => 65000, 'is_billable' => true, 'status' => 'submitted',
    ])->assertRedirect(route('matters.show', $matter, absolute: false));
    expect($timeEntry->refresh()->status)->toBe('submitted');

    $this->post(route('matters.workspace.store', [$matter, 'notes']), ['body' => 'Client requested a status update.'])
        ->assertRedirect(route('matters.show', $matter, absolute: false));
    $note = $matter->notes()->latest('id')->firstOrFail();
    expect($note->author_user_id)->toBe($administrator->id);
    $this->patch(route('matters.workspace.update', [$matter, 'notes', $note]), ['body' => 'Client requested an updated hearing schedule.'])
        ->assertRedirect(route('matters.show', $matter, absolute: false));
    expect($note->refresh()->body)->toBe('Client requested an updated hearing schedule.');

    $this->post(route('matters.workspace.store', [$matter, 'documents']), [
        'document' => UploadedFile::fake()->create('brief.pdf', 100, 'application/pdf'), 'category' => 'pleading', 'visibility' => 'internal',
    ])->assertRedirect(route('matters.show', $matter, absolute: false));
    $document = $matter->documents()->latest('id')->firstOrFail();
    Storage::disk('local')->assertExists($document->path);
    expect($document->companyDriveFolder)->not->toBeNull();
    expect($document->companyDriveFolder?->client_id)->toBe($matter->client_id);
    $this->get(route('matters.documents.download', [$matter, $document]))->assertDownload('brief.pdf');
    $this->patch(route('matters.workspace.update', [$matter, 'documents', $document]), [
        'name' => 'Filed brief', 'category' => 'pleading', 'visibility' => 'internal',
    ])->assertRedirect(route('matters.show', $matter, absolute: false));
    expect($document->refresh()->name)->toBe('Filed brief');

    $this->post(route('matters.workspace.store', [$matter, 'calendar-events']), [
        'title' => 'Client preparation meeting', 'type' => 'meeting', 'starts_at' => '2026-08-15 09:00:00', 'ends_at' => '2026-08-15 10:00:00', 'is_all_day' => false, 'attendee_ids' => [$administrator->id],
    ])->assertRedirect(route('matters.show', $matter, absolute: false));
    $appointment = $matter->appointments()->latest('id')->firstOrFail();
    expect($appointment->attendees()->pluck('id')->all())->toBe([$administrator->id]);
    $this->patch(route('matters.workspace.update', [$matter, 'calendar-events', $appointment]), [
        'title' => 'Updated client preparation meeting', 'type' => 'meeting', 'starts_at' => '2026-08-16 09:00:00', 'ends_at' => '2026-08-16 10:00:00', 'is_all_day' => false, 'attendee_ids' => [$administrator->id],
    ])->assertRedirect(route('matters.show', $matter, absolute: false));
    expect($appointment->refresh()->title)->toBe('Updated client preparation meeting');

    $this->post(route('matters.workspace.store', [$matter, 'expenses']), [
        'expense_category_id' => $expenseCategory->id, 'amount' => 50000, 'incurred_at' => '2026-08-12', 'is_billable' => true,
        'receipt' => UploadedFile::fake()->create('court-receipt.pdf', 100, 'application/pdf'),
    ])->assertRedirect(route('matters.show', $matter, absolute: false));
    $expense = $matter->expenses()->latest('id')->firstOrFail();
    expect($expense->expense_category_id)->toBe($expenseCategory->id);
    Storage::disk('local')->assertExists($expense->receipt_path);
    $this->get(route('matters.expenses.receipt', [$matter, $expense]))->assertDownload(basename($expense->receipt_path));
    $this->patch(route('matters.workspace.update', [$matter, 'expenses', $expense]), [
        'expense_category_id' => $expenseCategory->id, 'amount' => 55000, 'incurred_at' => '2026-08-13', 'is_billable' => true, 'vendor' => 'High Court Registry',
    ])->assertRedirect(route('matters.show', $matter, absolute: false));
    expect($expense->refresh()->amount)->toBe('55000.00');

    $this->post(route('matters.workspace.store', [$matter, 'flat-fees']), [
        'billing_item_id' => $billingItem->id, 'description' => 'Fixed consultation', 'quantity' => 2, 'unit_price' => 250000, 'tax_rate' => 16.5, 'charged_at' => '2026-08-12', 'status' => 'billable',
    ])->assertRedirect(route('matters.show', $matter, absolute: false));
    $charge = $matter->charges()->latest('id')->firstOrFail();
    expect($charge->amount)->toBe('582500.00');
    $this->patch(route('matters.workspace.update', [$matter, 'flat-fees', $charge]), [
        'billing_item_id' => $billingItem->id, 'description' => 'Updated fixed consultation', 'quantity' => 2, 'unit_price' => 250000, 'tax_rate' => 0, 'charged_at' => '2026-08-13', 'status' => 'billable',
    ])->assertRedirect(route('matters.show', $matter, absolute: false));
    expect($charge->refresh()->amount)->toBe('500000.00');

    $this->post(route('matters.workspace.store', [$matter, 'emails']), [
        'to' => 'client@example.test', 'subject' => 'Matter update', 'body' => '<p>Your matter is progressing.</p>', 'document_ids' => [$document->id],
    ])->assertRedirect(route('matters.show', $matter, absolute: false));
    $email = $matter->emails()->latest('id')->firstOrFail();
    expect($email->documents()->pluck('id')->all())->toBe([$document->id]);
    expect($email->status)->toBe('queued');
    Mail::assertQueued(MatterEmailMessage::class);

    $this->patch(route('matters.tasks.complete', [$matter, $task]))
        ->assertRedirect(route('matters.show', $matter, absolute: false));
    expect($task->refresh()->status)->toBe('completed');
    expect(ActivityLog::query()->where('matter_id', $matter->id)->count())->toBe(16);
});

test('administrators can view firm calendar activities across matters', function () {
    $administrator = administrationUser();
    $matter = Matter::factory()->create(['deadline_at' => '2027-08-25']);
    Appointment::query()->create([
        'matter_id' => $matter->id,
        'client_id' => $matter->client_id,
        'organizer_user_id' => $administrator->id,
        'title' => 'Case management conference',
        'type' => 'hearing',
        'status' => 'scheduled',
        'starts_at' => '2027-08-12 09:00:00',
        'ends_at' => '2027-08-12 10:00:00',
        'is_all_day' => false,
    ]);
    Task::query()->create([
        'matter_id' => $matter->id,
        'created_by_user_id' => $administrator->id,
        'title' => 'File witness bundle',
        'priority' => 'high',
        'due_at' => '2027-08-18',
    ]);

    $this->actingAs($administrator)
        ->get(route('calendar.index', ['month' => '2027-08']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('calendar/index')
            ->where('month', '2027-08')
            ->has('events', 3)
            ->where('events.0.title', 'Case management conference')
            ->where('events.1.title', 'File witness bundle')
            ->where('events.2.title', $matter->title));
});

test('administrators can view the company drive', function () {
    $organization = Client::factory()->create(['organization_name' => 'Company Drive Organization']);
    $client = Client::factory()->create([
        'type' => 'individual',
        'full_name' => 'Company Drive Client',
        'organization_name' => null,
    ]);
    $organizationFolder = app(EnsureClientDriveFolder::class)->handle($organization);
    $clientFolder = app(EnsureClientDriveFolder::class)->handle($client);
    Document::query()->create([
        'client_id' => $client->id,
        'company_drive_folder_id' => $clientFolder->id,
        'name' => 'Client onboarding notes',
        'category' => 'general',
        'visibility' => 'internal',
        'disk' => 'local',
        'path' => 'clients/onboarding-notes.pdf',
        'original_filename' => 'onboarding-notes.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 2048,
    ]);
    Document::query()->create([
        'client_id' => $organization->id,
        'company_drive_folder_id' => $organizationFolder->id,
        'name' => 'Organization compliance register',
        'category' => 'general',
        'visibility' => 'internal',
        'disk' => 'local',
        'path' => 'organizations/compliance-register.pdf',
        'original_filename' => 'compliance-register.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 4096,
    ]);

    $this->actingAs(administrationUser())
        ->get(route('files.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('files/index')
            ->where('clientFolders', fn (Collection $clientFolders): bool => $clientFolders
                ->contains(fn (array $folder): bool => $folder['id'] === $clientFolder->id
                    && $folder['documentCount'] === 1
                    && $folder['documents'][0]['name'] === 'Client onboarding notes'))
            ->where('organizationFolders', fn (Collection $organizationFolders): bool => $organizationFolders
                ->contains(fn (array $folder): bool => $folder['id'] === $organizationFolder->id
                    && $folder['documentCount'] === 1
                    && $folder['documents'][0]['name'] === 'Organization compliance register')));
});

test('administrators upload and download company-drive documents through the private R2 disk', function () {
    Storage::fake('cloudflare_r2');
    $administrator = administrationUser();
    $client = Client::factory()->create();
    $folder = app(EnsureClientDriveFolder::class)->handle($client, $administrator);
    $file = UploadedFile::fake()->create('engagement-letter.pdf', 120, 'application/pdf');

    $this->actingAs($administrator)
        ->post(route('files.store'), [
            'folder_id' => $folder->id,
            'documents' => [$file],
        ])
        ->assertRedirect();

    $document = Document::query()->sole();

    expect($document->disk)->toBe('cloudflare_r2')
        ->and($document->company_drive_folder_id)->toBe($folder->id)
        ->and($document->client_id)->toBe($client->id);
    Storage::disk('cloudflare_r2')->assertExists($document->path);

    $this->get(route('files.download', $document))
        ->assertOk()
        ->assertDownload('engagement-letter.pdf');
});

test('matter workspace records cannot be accessed through a different matter', function () {
    $administrator = administrationUser();
    $firstMatter = Matter::factory()->create();
    $secondMatter = Matter::factory()->create();
    $task = Task::query()->create(['matter_id' => $firstMatter->id, 'title' => 'Protected task']);
    $document = Document::query()->create([
        'matter_id' => $firstMatter->id,
        'client_id' => $firstMatter->client_id,
        'name' => 'Protected document',
        'category' => 'general',
        'visibility' => 'internal',
        'disk' => 'local',
        'path' => 'matters/protected-document.pdf',
        'original_filename' => 'protected-document.pdf',
    ]);

    $this->actingAs($administrator)
        ->patch(route('matters.tasks.complete', [$secondMatter, $task]))
        ->assertNotFound();
    $this->patch(route('matters.workspace.update', [$secondMatter, 'tasks', $task]), [
        'title' => 'Attempted cross-matter update',
        'priority' => 'normal',
    ])
        ->assertNotFound();
    $this->get(route('matters.documents.download', [$secondMatter, $document]))
        ->assertNotFound();
});

test('the database includes 30 representative clients and 100 distinct sample matters', function () {
    $sampleClients = Client::query()
        ->whereJsonContains('metadata->source', 'practice-sample-data')
        ->get();
    $sampleMatters = Matter::query()
        ->whereJsonContains('metadata->source', 'practice-sample-data')
        ->withCount([
            'tasks',
            'timeEntries',
            'notes',
            'appointments',
            'expenses',
            'charges',
        ])
        ->get(['id', 'client_id', 'matter_type_id', 'responsible_user_id', 'matter_number', 'title', 'deadline_at']);

    expect($sampleClients)->toHaveCount(30);
    expect($sampleMatters)->toHaveCount(100);
    expect($sampleMatters->pluck('title')->unique())->toHaveCount(100);
    expect($sampleMatters->pluck('client_id')->unique())->toHaveCount(30);
    expect($sampleMatters->pluck('matter_type_id')->filter()->unique())->toHaveCount(8);
    expect($sampleMatters->pluck('responsible_user_id')->filter()->unique())->toHaveCount(10);
    expect($sampleMatters->whereNull('deadline_at'))->toBeEmpty();
    expect($sampleMatters->filter(
        fn (Matter $matter): bool => $matter->deadline_at?->isBefore(now()->startOfDay()) ?? false,
    ))->toHaveCount(5);
    expect($sampleMatters->every(fn (Matter $matter): bool => $matter->tasks_count +
        $matter->time_entries_count +
        $matter->notes_count +
        $matter->appointments_count +
        $matter->expenses_count +
        $matter->charges_count >= 2 &&
        $matter->tasks_count +
        $matter->time_entries_count +
        $matter->notes_count +
        $matter->appointments_count +
        $matter->expenses_count +
        $matter->charges_count <= 10,
    ))->toBeTrue();
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

    foreach (['Family Testament', 'Family Testament!'] as $name) {
        $this->post(route('administration.reference-data.store', ['resource' => 'matter-types']), [
            'name' => $name,
            'code' => 'manual-code',
            'default_billing_type' => 'hourly',
            'is_active' => true,
            'sort_order' => 0,
        ])->assertRedirect(route('administration.matter-types.index', absolute: false));
    }

    expect(MatterType::query()
        ->whereIn('name', ['Family Testament', 'Family Testament!'])
        ->orderBy('code')
        ->pluck('code')
        ->all())
        ->toBe(['family-testament', 'family-testament-2']);
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
