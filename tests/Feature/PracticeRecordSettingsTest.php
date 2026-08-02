<?php

use App\Actions\Clients\GenerateClientNumber;
use App\Actions\Matters\GenerateMatterNumber;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Department;
use App\Models\GeneralSetting;
use App\Models\Matter;
use App\Models\MatterType;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function practiceSettingsAdministrator(): User
{
    $permission = Permission::query()->create([
        'key' => 'administration.access',
        'name' => 'Access administration',
        'group' => 'Administration',
    ]);
    $role = Role::query()->create(['name' => 'Practice settings administrator']);
    $role->assignedPermissions()->attach($permission);

    $user = User::factory()->create();
    $user->roles()->attach($role);

    return grantAllPracticePermissions($user);
}

/** @return array<string, mixed> */
function clientSettingsPayload(array $fields = []): array
{
    return [
        'number_prefix' => 'CLT',
        'number_padding' => 4,
        'next_number' => 1,
        'fields' => $fields,
    ];
}

/** @return array<string, mixed> */
function matterSettingsPayload(array $fields = []): array
{
    return [
        'number_prefix' => 'MATX',
        'number_padding' => 4,
        'next_number' => 1,
        'fields' => $fields,
    ];
}

test('administrators can configure client and matter numbering and custom fields', function () {
    $administrator = practiceSettingsAdministrator();

    $this->actingAs($administrator)
        ->get(route('clients.settings.edit'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('clients/settings')
            ->where('settings.number_prefix', 'CLI'));

    $this->put(route('clients.settings.update'), clientSettingsPayload([[
        'id' => 'new-client-industry',
        'label' => 'Industry',
        'type' => 'select',
        'placeholder' => '',
        'options' => ['Agriculture', 'Technology'],
        'required' => true,
        'applies_to' => ['organization'],
        'step' => 2,
    ]]))->assertRedirect(route('clients.settings.edit'));

    $this->put(route('matters.settings.update'), matterSettingsPayload([[
        'id' => 'new-matter-court',
        'label' => 'Court file number',
        'type' => 'text',
        'placeholder' => 'HC/LIL/CIV/001/2026',
        'options' => [],
        'required' => true,
        'step' => 3,
    ]]))->assertRedirect(route('matters.settings.edit'));

    $settings = GeneralSetting::query()->sole();

    expect($settings->client_settings['number_prefix'])->toBe('CLT');
    expect($settings->client_settings['fields'][0]['key'])->toStartWith('custom_');
    expect($settings->client_settings['fields'][0]['step'])->toBe(2);
    expect($settings->matter_settings['fields'][0]['step'])->toBe(3);
    expect(ActivityLog::query()->where('event', 'client.settings_updated')->exists())->toBeTrue();
    expect(ActivityLog::query()->where('event', 'matter.settings_updated')->exists())->toBeTrue();
});

test('configured client fields are scoped, validated, and stored in client metadata', function () {
    $administrator = practiceSettingsAdministrator();
    $this->actingAs($administrator)->put(route('clients.settings.update'), clientSettingsPayload([[
        'id' => 'new-client-industry',
        'label' => 'Industry',
        'type' => 'select',
        'placeholder' => '',
        'options' => ['Agriculture', 'Technology'],
        'required' => true,
        'applies_to' => ['organization'],
    ]]));
    $key = GeneralSetting::query()->sole()->client_settings['fields'][0]['key'];

    $this->post(route('clients.store'), [
        'type' => 'organization',
        'name' => 'Zomba Estates Limited',
        'status' => 'active',
        'custom_fields' => [$key => 'Technology'],
    ])->assertRedirect(route('clients.index'));

    $client = Client::query()->where('organization_name', 'Zomba Estates Limited')->sole();

    expect($client->metadata['custom_fields'][$key])->toBe('Technology');

    $this->patch(route('clients.update', $client), [
        'type' => 'organization',
        'name' => 'Zomba Estates Limited',
        'status' => 'active',
        'custom_fields' => [$key => 'Agriculture'],
    ])->assertRedirect(route('clients.index'));

    expect($client->refresh()->metadata['custom_fields'][$key])->toBe('Agriculture');

    $this->post(route('clients.store'), [
        'type' => 'organization',
        'name' => 'Mchinji Estates Limited',
        'status' => 'active',
        'custom_fields' => [$key => 'Manufacturing'],
    ])->assertSessionHasErrors("custom_fields.$key");

    $this->post(route('clients.store'), [
        'type' => 'individual',
        'name' => 'Tapiwa Moyo',
        'status' => 'active',
        'custom_fields' => [$key => 'Technology'],
    ])->assertSessionHasErrors('custom_fields');

    $this->put(route('clients.settings.update'), clientSettingsPayload())->assertRedirect();
    expect($client->refresh()->metadata['custom_fields'][$key])->toBe('Agriculture');
});

test('matter field values are captured in the opening workflow and exposed in the workspace', function () {
    $administrator = practiceSettingsAdministrator();
    $this->actingAs($administrator)->put(route('matters.settings.update'), matterSettingsPayload([[
        'id' => 'new-matter-court',
        'label' => 'Court file number',
        'type' => 'text',
        'placeholder' => '',
        'options' => [],
        'required' => true,
        'step' => 3,
    ]]));
    $key = GeneralSetting::query()->sole()->matter_settings['fields'][0]['key'];
    $client = Client::factory()->create();
    $department = Department::factory()->create();
    $position = Position::factory()->create([
        'department_id' => $department->id,
        'is_attorney' => true,
    ]);
    $attorney = User::factory()->create([
        'department_id' => $department->id,
        'position_id' => $position->id,
        'status' => 'active',
    ]);
    $matterType = MatterType::query()->create([
        'name' => 'Test litigation',
        'code' => 'test-litigation',
        'default_billing_type' => 'hourly',
        'default_hourly_rate' => 100000,
        'is_active' => true,
    ]);

    $this->post(route('matters.store'), [
        'client_id' => $client->id,
        'matter_type_id' => $matterType->id,
        'responsible_user_id' => $attorney->id,
        'title' => 'Commercial dispute',
        'status' => 'open',
        'priority' => 'normal',
        'billing_type' => 'hourly',
        'default_hourly_rate' => 100000,
        'opened_at' => '2026-08-02',
        'custom_fields' => [$key => 'HC/LIL/CIV/001/2026'],
    ])->assertRedirect(route('matters.index'));

    $matter = Matter::query()->where('title', 'Commercial dispute')->sole();

    expect($matter->metadata['custom_fields'][$key])->toBe('HC/LIL/CIV/001/2026');

    $this->get(route('matters.show', $matter))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('matters/show')
            ->where("matter.metadata.custom_fields.$key", 'HC/LIL/CIV/001/2026')
            ->where('customFields.0.key', $key));
});

test('client and matter generators use configured numbers and skip existing records', function () {
    $administrator = practiceSettingsAdministrator();
    $this->actingAs($administrator)->put(route('clients.settings.update'), clientSettingsPayload());
    $this->put(route('matters.settings.update'), matterSettingsPayload());
    Client::factory()->create(['client_number' => 'CLT-0001']);
    Matter::factory()->create(['matter_number' => 'MATX-0001']);

    expect(DB::transaction(fn (): string => app(GenerateClientNumber::class)->handle()))
        ->toBe('CLT-0002');
    expect(DB::transaction(fn (): string => app(GenerateMatterNumber::class)->handle()))
        ->toBe('MATX-0002');
});
