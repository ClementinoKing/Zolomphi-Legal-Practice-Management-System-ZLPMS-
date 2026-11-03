<?php

use App\Models\Client;
use App\Models\GeneralSetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Matter;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\InvoiceSettings;

test('invoice viewers can print a formatted client invoice with payment and tax details', function () {
    $user = User::factory()->create();
    grantAllPracticePermissions($user);
    $client = Client::factory()->create([
        'type' => 'organization',
        'organization_name' => 'Mota Holdings',
        'contact_person' => 'Brian Katimba',
        'address_line1' => 'P. O. Box 31379',
        'city' => 'Lilongwe',
        'country' => 'Malawi',
        'phone' => '+265 888 000 111',
        'email' => 'accounts@example.test',
    ]);
    $matter = Matter::factory()->create([
        'client_id' => $client->id,
        'matter_number' => 'ZC/Com/2026/04/1085',
        'title' => 'Civil Case Number 655 of 2026',
    ]);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'matter_id' => $matter->id,
        'invoice_number' => 'ZC-INV-0042',
        'our_reference' => 'ZC/Com/2026/04/1085',
        'your_reference' => 'CLIENT-REF-9',
        'subtotal' => 2_850_000,
        'tax_amount' => 498_750,
        'total_amount' => 3_348_750,
        'balance_due' => 3_348_750,
        'tax_breakdown' => [
            ['label' => 'VAT', 'rate' => '16.50', 'amount' => '470250.00'],
            ['label' => 'MLS Levy', 'rate' => '1.00', 'amount' => '28500.00'],
        ],
        'terms' => 'Payable on first presentation.',
    ]);
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'description' => 'Professional charges — preparing and filing the defence',
        'quantity' => 1,
        'unit_price' => 1_900_000,
        'amount' => 1_900_000,
        'sort_order' => 0,
    ]);
    GeneralSetting::query()->create([
        'firm_name' => 'Zolomphi and Company',
        'invoice_contact_name' => 'Misozi Jacob',
        'email' => 'billing@zolomphi.example',
        'phone' => '+265 999 000 222',
        'address_line1' => 'Capital City',
        'currency' => 'MWK',
        'invoice_settings' => [
            ...app(InvoiceSettings::class)->values(),
            'payment' => [
                'instructions' => 'Please pay by bank transfer.',
                'bank_name' => 'Sample Bank',
                'account_name' => 'Zolomphi and Company',
                'account_number' => 'TEST-ACCOUNT-123',
                'account_branch' => 'Capital City',
                'swift_code' => 'TESTMWMW',
            ],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('invoices.print', $invoice))
        ->assertOk()
        ->assertSee('INVOICE')
        ->assertSee('ZC-INV-0042')
        ->assertSee('Mota Holdings')
        ->assertSee('Attn. Brian Katimba')
        ->assertSee('Our Ref:')
        ->assertSee('CLIENT-REF-9')
        ->assertSee('ZC/Com/2026/04/1085')
        ->assertSee('Professional charges — preparing and filing the defence')
        ->assertSee('VAT (16.5%)')
        ->assertSee('MLS Levy (1%)')
        ->assertSee('3,348,750.00')
        ->assertSee('TEST-ACCOUNT-123')
        ->assertSee('Invoice contact')
        ->assertSee('Misozi Jacob')
        ->assertSee('billing@zolomphi.example')
        ->assertSee('Payable on first presentation.');
});

test('invoice print view respects invoice and matter visibility permissions', function () {
    $viewer = User::factory()->create();
    $permission = Permission::query()->firstOrCreate(
        ['key' => 'invoices.view'],
        ['name' => 'View invoices', 'group' => 'Billing'],
    );
    $role = Role::query()->create(['name' => 'Invoice read-only viewer']);
    $role->assignedPermissions()->attach($permission);
    $viewer->roles()->attach($role);

    $client = Client::factory()->create();
    $otherUser = User::factory()->create();
    $matter = Matter::factory()->create([
        'client_id' => $client->id,
        'responsible_user_id' => $otherUser->id,
    ]);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'matter_id' => $matter->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('invoices.print', $invoice))
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->get(route('invoices.print', $invoice))
        ->assertForbidden();
});

test('invoice tax defaults use the sample rates and payment details start blank', function () {
    $settings = app(InvoiceSettings::class)->values();

    expect($settings['tax_enabled'])->toBeTrue()
        ->and($settings['tax_label'])->toBe('VAT')
        ->and($settings['default_tax_rate'])->toBe('16.50')
        ->and($settings['levy_enabled'])->toBeTrue()
        ->and($settings['levy_label'])->toBe('MLS Levy')
        ->and($settings['levy_rate'])->toBe('1.00')
        ->and($settings['payment']['bank_name'])->toBe('')
        ->and($settings['payment']['account_number'])->toBe('');
});
