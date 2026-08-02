<?php

use App\Models\Document;
use App\Models\GeneralSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function invoiceLogoAdministrator(): User
{
    $permission = Permission::query()->create([
        'key' => 'administration.access',
        'name' => 'Access administration',
        'group' => 'Administration',
    ]);
    $role = Role::query()->create(['name' => 'Invoice logo administrator']);
    $role->assignedPermissions()->attach($permission);

    $user = User::factory()->create();
    $user->roles()->attach($role);

    return $user;
}

/** @return array<string, mixed> */
function invoiceTemplateSettingsPayload(): array
{
    return [
        'tax_enabled' => true,
        'tax_label' => 'VAT',
        'default_tax_rate' => '16.5',
        'number_prefix' => 'INV',
        'number_padding' => 6,
        'next_number' => 1,
        'default_terms' => 'Payment is due within 30 days.',
        'template' => [
            'layout' => 'classic',
            'accent_color' => '#191E5E',
            'header_note' => 'Thank you for your instructions.',
            'footer_note' => 'Please use the invoice number as your payment reference.',
            'show_tax_id' => true,
            'show_matter_reference' => true,
            'logo' => ['id' => 'system:zolomphi-logo'],
        ],
    ];
}

test('administrators can select an image from the system logo library', function () {
    $administrator = invoiceLogoAdministrator();

    $this->actingAs($administrator)
        ->get(route('invoices.settings.edit'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('invoices/settings')
            ->has('logos', 3)
            ->where('logos.0.id', 'system:zolomphi-logo'));

    $this->put(route('invoices.settings.update'), invoiceTemplateSettingsPayload())
        ->assertRedirect(route('invoices.settings.edit'));

    expect(data_get(
        GeneralSetting::query()->sole()->invoice_settings,
        'template.logo.id',
    ))->toBe('system:zolomphi-logo');
});

test('an uploaded invoice logo is stored in the configured company drive disk and selected for the template', function () {
    Storage::fake('company-drive');
    config()->set('filesystems.company_drive_disk', 'company-drive');
    $administrator = invoiceLogoAdministrator();
    $payload = invoiceTemplateSettingsPayload();
    $payload['logo_file'] = UploadedFile::fake()->image('firm-logo.png', 600, 240);

    $this->actingAs($administrator)
        ->put(route('invoices.settings.update'), $payload)
        ->assertRedirect(route('invoices.settings.edit'));

    $document = Document::query()->sole();
    $settings = GeneralSetting::query()->sole();

    Storage::disk('company-drive')->assertExists($document->path);
    expect($document->category)->toBe('invoice-template-logo');
    expect(data_get($settings->invoice_settings, 'template.logo.id'))
        ->toBe('document:'.$document->id);

    $this->get(route('files.preview', $document))
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});
