<?php

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Matter;
use App\Models\MatterCharge;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function invoiceAdministrator(): User
{
    $permission = Permission::query()->create([
        'key' => 'administration.access',
        'name' => 'Access administration',
        'group' => 'Administration',
    ]);
    $role = Role::query()->create(['name' => 'Invoice Administrator']);
    $role->assignedPermissions()->attach($permission);

    $user = User::factory()->create();
    $user->roles()->attach($role);

    return grantAllPracticePermissions($user);
}

function invoiceMatter(Client $client): Matter
{
    return Matter::factory()->create([
        'client_id' => $client->id,
        'default_hourly_rate' => 100,
    ]);
}

function draftPayload(Client $client, array $items, ?Matter $matter = null, string $currency = 'MWK'): array
{
    return [
        'client_id' => $client->id,
        'matter_id' => $matter?->id,
        'currency' => $currency,
        'issued_at' => '2026-08-01',
        'due_at' => '2026-08-31',
        'notes' => 'Thank you for your instructions.',
        'terms' => 'Payment is due within 30 days.',
        'items' => $items,
    ];
}

test('administrators can build a draft invoice from eligible work and manual lines', function () {
    $administrator = invoiceAdministrator();
    $client = Client::factory()->create();
    $firstMatter = invoiceMatter($client);
    $secondMatter = invoiceMatter($client);
    $timeEntry = TimeEntry::query()->create([
        'matter_id' => $firstMatter->id,
        'user_id' => $administrator->id,
        'entry_date' => '2026-07-28',
        'description' => 'Reviewed the signed commercial agreement.',
        'hours' => 2,
        'hourly_rate' => 100,
        'is_billable' => true,
        'status' => 'approved',
    ]);
    $expense = Expense::query()->create([
        'matter_id' => $secondMatter->id,
        'recorded_by_user_id' => $administrator->id,
        'expense_number' => 'EXP-TEST-001',
        'category' => 'Court filing',
        'currency' => 'MWK',
        'amount' => 50,
        'incurred_at' => '2026-07-29',
        'is_billable' => true,
        'status' => 'recorded',
    ]);
    $flatFee = MatterCharge::query()->create([
        'matter_id' => $secondMatter->id,
        'created_by_user_id' => $administrator->id,
        'description' => 'Fixed fee for corporate filing.',
        'quantity' => 3,
        'unit_price' => 50,
        'tax_rate' => 16,
        'amount' => 174,
        'status' => 'billable',
        'charged_at' => '2026-07-30',
    ]);

    $this->actingAs($administrator)
        ->post(route('invoices.store'), draftPayload($client, [
            ['source_type' => 'time_entry', 'source_id' => $timeEntry->id, 'description' => 'Agreement review', 'quantity' => 99, 'unit_price' => 1, 'tax_rate' => 50],
            ['source_type' => 'expense', 'source_id' => $expense->id, 'description' => 'Court filing disbursement', 'quantity' => 1, 'unit_price' => 1, 'tax_rate' => 50],
            ['source_type' => 'matter_charge', 'source_id' => $flatFee->id, 'description' => 'Corporate filing', 'quantity' => 1, 'unit_price' => 1, 'tax_rate' => 0],
            ['source_type' => 'manual', 'source_id' => null, 'description' => 'Administrative delivery', 'quantity' => 2, 'unit_price' => 10, 'tax_rate' => 10],
        ]))
        ->assertRedirect();

    $invoice = Invoice::query()->with('items')->sole();

    expect($invoice->invoice_number)->toBe('INV-000001');
    expect($invoice->status)->toBe('draft');
    expect($invoice->matter_id)->toBeNull();
    expect($invoice->subtotal)->toBe('420.00');
    expect($invoice->tax_amount)->toBe('26.00');
    expect($invoice->total_amount)->toBe('446.00');
    expect($invoice->balance_due)->toBe('446.00');
    expect($invoice->items)->toHaveCount(4);
    expect($invoice->items->firstWhere('time_entry_id', $timeEntry->id)->quantity)->toBe('2.00');
    expect($invoice->items->firstWhere('time_entry_id', $timeEntry->id)->unit_price)->toBe('100.00');
    expect($invoice->items->firstWhere('expense_id', $expense->id))->not->toBeNull();
    expect($invoice->items->firstWhere('matter_charge_id', $flatFee->id))->not->toBeNull();
    expect(ActivityLog::query()->where('event', 'invoice.created')->where('subject_id', $invoice->id)->exists())->toBeTrue();
});

test('approved source amounts are copied into a draft in the selected firm currency', function () {
    config()->set('services.exchange_rate.key', 'test-key');
    config()->set('services.exchange_rate.base_url', 'https://rates.example.test/v6');
    Cache::flush();
    Http::preventStrayRequests();
    Http::fake([
        'https://rates.example.test/v6/test-key/latest/MWK' => Http::response([
            'result' => 'success',
            'conversion_rates' => ['MWK' => 1, 'USD' => 0.00058],
        ]),
    ]);
    $administrator = invoiceAdministrator();
    $client = Client::factory()->create();
    $matter = invoiceMatter($client);
    $timeEntry = TimeEntry::query()->create([
        'matter_id' => $matter->id,
        'user_id' => $administrator->id,
        'entry_date' => '2026-07-28',
        'description' => 'Reviewed instructions.',
        'hours' => 2,
        'hourly_rate' => 1000,
        'is_billable' => true,
        'status' => 'approved',
    ]);

    $this->actingAs($administrator)
        ->post(route('invoices.store'), draftPayload($client, [
            ['source_type' => 'time_entry', 'source_id' => $timeEntry->id, 'description' => 'Instructions review', 'quantity' => 2, 'unit_price' => 1000, 'tax_rate' => 0],
        ], $matter, 'USD'))
        ->assertRedirect();

    $invoice = Invoice::query()->with('items')->sole();

    expect($invoice->currency)->toBe('USD');
    expect($invoice->items->sole()->unit_price)->toBe('0.58');
    expect($invoice->total_amount)->toBe('1.16');
});

test('drafts can be updated and removed source records become available again', function () {
    $administrator = invoiceAdministrator();
    $client = Client::factory()->create();
    $matter = invoiceMatter($client);
    $expense = Expense::query()->create([
        'matter_id' => $matter->id,
        'recorded_by_user_id' => $administrator->id,
        'expense_number' => 'EXP-TEST-002',
        'category' => 'Courier',
        'currency' => 'MWK',
        'amount' => 250,
        'incurred_at' => '2026-07-29',
        'is_billable' => true,
        'status' => 'recorded',
    ]);

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'matter_id' => $matter->id,
        'created_by_user_id' => $administrator->id,
    ]);
    $invoice->items()->create([
        'expense_id' => $expense->id,
        'description' => 'Courier',
        'quantity' => 1,
        'unit_price' => 250,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'amount' => 250,
        'sort_order' => 0,
    ]);

    $this->actingAs($administrator)
        ->patch(route('invoices.update', $invoice), draftPayload($client, [
            ['source_type' => 'manual', 'source_id' => null, 'description' => 'Updated drafting charge', 'quantity' => 1, 'unit_price' => 500, 'tax_rate' => 0],
        ], $matter))
        ->assertRedirect(route('invoices.edit', $invoice, absolute: false));

    expect($invoice->refresh()->total_amount)->toBe('500.00');
    expect($invoice->items()->sole()->expense_id)->toBeNull();
    expect(ActivityLog::query()->where('event', 'invoice.updated')->where('subject_id', $invoice->id)->exists())->toBeTrue();

    $this->get(route('invoices.create', ['client_id' => $client->id, 'matter_id' => $matter->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('invoices/builder')
            ->has('availableSources.expenses', 1)
            ->where('availableSources.expenses.0.id', $expense->id),
        );
});

test('invoice source records must be eligible, belong to the client, and remain uninvoiced', function () {
    $administrator = invoiceAdministrator();
    $client = Client::factory()->create();
    $otherClient = Client::factory()->create();
    $matter = invoiceMatter($client);
    $otherMatter = invoiceMatter($otherClient);
    $unapprovedTime = TimeEntry::query()->create([
        'matter_id' => $matter->id,
        'user_id' => $administrator->id,
        'entry_date' => '2026-07-28',
        'description' => 'Unapproved time',
        'hours' => 1,
        'hourly_rate' => 100,
        'is_billable' => true,
        'status' => 'submitted',
    ]);
    $otherExpense = Expense::query()->create([
        'matter_id' => $otherMatter->id,
        'recorded_by_user_id' => $administrator->id,
        'expense_number' => 'EXP-TEST-003',
        'category' => 'Travel',
        'currency' => 'MWK',
        'amount' => 100,
        'incurred_at' => '2026-07-29',
        'is_billable' => true,
        'status' => 'recorded',
    ]);

    $this->actingAs($administrator)
        ->post(route('invoices.store'), draftPayload($client, [
            ['source_type' => 'time_entry', 'source_id' => $unapprovedTime->id, 'description' => 'Unapproved time', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0],
        ], $matter))
        ->assertSessionHasErrors('items.0.source_id');

    $this->post(route('invoices.store'), draftPayload($client, [
        ['source_type' => 'expense', 'source_id' => $otherExpense->id, 'description' => 'Other client expense', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0],
    ]))->assertSessionHasErrors('items.0.source_id');

    $invoicedExpense = Expense::query()->create([
        'matter_id' => $matter->id,
        'recorded_by_user_id' => $administrator->id,
        'expense_number' => 'EXP-TEST-004',
        'category' => 'Filing',
        'currency' => 'MWK',
        'amount' => 100,
        'incurred_at' => '2026-07-29',
        'is_billable' => true,
        'status' => 'recorded',
    ]);
    Invoice::factory()
        ->for($client)
        ->create()
        ->items()
        ->create([
            'expense_id' => $invoicedExpense->id,
            'description' => 'Already billed',
            'quantity' => 1,
            'unit_price' => 100,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'amount' => 100,
        ]);

    $this->post(route('invoices.store'), draftPayload($client, [
        ['source_type' => 'expense', 'source_id' => $invoicedExpense->id, 'description' => 'Already billed', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0],
    ], $matter))->assertSessionHasErrors('items.0.source_id');
});

test('invoice pages require administration access', function () {
    $this->get(route('invoices.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())
        ->get(route('invoices.index'))
        ->assertForbidden();
});
