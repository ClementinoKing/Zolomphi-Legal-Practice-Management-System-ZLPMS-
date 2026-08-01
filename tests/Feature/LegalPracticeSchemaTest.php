<?php

use Illuminate\Support\Facades\Schema;

test('creates the legal practice management schema', function () {
    $tables = [
        'roles',
        'role_user',
        'general_settings',
        'practice_areas',
        'clients',
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

test('adds user profile fields and indexes the primary operational queues', function () {
    expect(Schema::hasColumns('users', [
        'phone',
        'department',
        'status',
        'notification_settings',
        'session_timeout',
    ]))->toBeTrue();

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
