<?php

use App\Models\Client;
use Illuminate\Support\Facades\Schema;

test('creates the legal practice management schema', function () {
    $tables = [
        'roles',
        'role_user',
        'general_settings',
        'practice_areas',
        'clients',
        'client_contact_points',
        'client_duplicate_reviews',
        'client_duplicate_name_keys',
        'matters',
        'matter_practice_area',
        'matter_parties',
        'court_cases',
        'appointments',
        'tasks',
        'task_user',
        'documents',
        'time_entries',
        'invoices',
        'invoice_items',
        'payments',
        'expenses',
        'activity_logs',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
});

test('client contact details are stored as reusable unique contact points', function () {
    expect(Schema::hasColumns('clients', [
        'normalized_name',
        'email_contact_id',
        'phone_contact_id',
        'alternate_phone_contact_id',
    ]))->toBeTrue();
    expect(Schema::hasColumns('client_contact_points', [
        'type',
        'value',
        'normalized_value',
    ]))->toBeTrue();

    $contactIndexes = collect(Schema::getIndexes('client_contact_points'));

    expect($contactIndexes->contains(
        fn (array $index): bool => $index['unique'] && $index['columns'] === ['type', 'normalized_value'],
    ))->toBeTrue();

    $clientIndexes = collect(Schema::getIndexes('clients'));

    foreach (['email_contact_id', 'phone_contact_id', 'alternate_phone_contact_id'] as $column) {
        expect($clientIndexes->contains(
            fn (array $index): bool => $index['columns'] === [$column],
        ))->toBeTrue();
    }

    expect(collect(Schema::getIndexes('client_duplicate_reviews'))->contains(
        fn (array $index): bool => $index['columns'] === ['candidate_client_id'],
    ))->toBeTrue();

    $nameIndexes = collect(Schema::getIndexes('client_duplicate_name_keys'));

    expect($nameIndexes->contains(
        fn (array $index): bool => $index['unique'] && $index['columns'] === ['normalized_name'],
    ))->toBeTrue();
});

test('legacy client contact columns are migrated to canonical contact references', function () {
    $client = Client::query()->whereNotNull('email_contact_id')->firstOrFail();

    expect(Schema::hasColumn('clients', 'email'))->toBeFalse();
    expect(Schema::hasColumn('clients', 'phone'))->toBeFalse();
    expect($client->email)->toBeString()->not->toBeEmpty();
});

test('adds user profile fields and indexes the primary operational queues', function () {
    expect(Schema::hasColumns('users', [
        'phone',
        'department',
        'status',
        'notification_settings',
        'session_timeout',
    ]))->toBeTrue();

    expect(Schema::hasColumn('general_settings', 'invoice_contact_name'))->toBeTrue();

    expect(Schema::hasColumns('matters', [
        'matter_number',
        'client_id',
        'responsible_user_id',
        'status',
        'opened_at',
    ]))->toBeTrue();

    $matterIndexes = collect(Schema::getIndexes('matters'));

    expect($matterIndexes->contains(
        fn (array $index): bool => $index['columns'] === ['client_id', 'status'],
    ))->toBeTrue();
    expect($matterIndexes->contains(
        fn (array $index): bool => $index['columns'] === ['responsible_user_id', 'status'],
    ))->toBeTrue();
});
