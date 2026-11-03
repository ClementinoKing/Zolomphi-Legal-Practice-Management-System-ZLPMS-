<?php

use App\Actions\Invoices\GenerateInvoiceNumber;
use App\Models\ActivityLog;
use App\Models\GeneralSetting;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function invoiceSettingsAdministrator(): User
{
    $permission = Permission::query()->create([
        'key' => 'administration.access',
        'name' => 'Access administration',
        'group' => 'Administration',
    ]);
    $role = Role::query()->create(['name' => 'Invoice settings administrator']);
    $role->assignedPermissions()->attach($permission);

    $user = User::factory()->create();
    $user->roles()->attach($role);

    return grantAllPracticePermissions($user);
}

test('administrators can configure invoice tax, numbering, and document template defaults', function () {
    $administrator = invoiceSettingsAdministrator();

    $this->actingAs($administrator)
        ->get(route('invoices.settings.edit'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('invoices/settings')
            ->where('settings.number_prefix', 'INV')
            ->where('settings.template.layout', 'classic'));

    $payload = [
        'tax_enabled' => true,
        'tax_label' => 'VAT',
        'default_tax_rate' => '16.5',
        'levy_enabled' => true,
        'levy_label' => 'MLS Levy',
        'levy_rate' => '1',
        'number_prefix' => 'ZC-INV',
        'number_padding' => 4,
        'next_number' => 42,
        'default_terms' => 'Payment is due within fourteen days.',
        'payment' => [
            'instructions' => 'Please pay by bank transfer.',
            'bank_name' => 'National Bank',
            'account_name' => 'Zolomphi and Company',
            'account_number' => '1234567890',
            'account_branch' => 'Capital City',
            'swift_code' => 'NBMAMWMW',
        ],
        'template' => [
            'layout' => 'modern',
            'accent_color' => '#EA702D',
            'header_note' => 'Thank you for your instructions.',
            'footer_note' => 'Use the invoice number as the payment reference.',
            'show_tax_id' => true,
            'show_matter_reference' => false,
            'logo' => null,
        ],
    ];

    $this->put(route('invoices.settings.update'), $payload)
        ->assertRedirect(route('invoices.settings.edit'));

    $settings = GeneralSetting::query()->sole();

    expect($settings->invoice_settings)->toMatchArray($payload);
    Invoice::factory()->create(['invoice_number' => 'ZC-INV-0042']);
    expect(DB::transaction(fn (): string => app(GenerateInvoiceNumber::class)->handle()))
        ->toBe('ZC-INV-0043');
    expect($settings->refresh()->invoice_settings['next_number'])->toBe(44);
    expect(ActivityLog::query()->where('event', 'invoice.settings_updated')->exists())->toBeTrue();
});

test('invoice settings require administrative access and valid numbering configuration', function () {
    $this->get(route('invoices.settings.edit'))->assertRedirect(route('login'));

    $administrator = invoiceSettingsAdministrator();

    $this->actingAs($administrator)
        ->put(route('invoices.settings.update'), [
            'tax_enabled' => true,
            'tax_label' => 'VAT',
            'default_tax_rate' => 0,
            'levy_enabled' => true,
            'levy_label' => 'MLS Levy',
            'levy_rate' => 1,
            'number_prefix' => 'invalid prefix!',
            'number_padding' => 2,
            'next_number' => 0,
            'template' => [],
            'payment' => [],
        ])
        ->assertSessionHasErrors([
            'number_prefix',
            'number_padding',
            'next_number',
            'template.layout',
        ]);
});
