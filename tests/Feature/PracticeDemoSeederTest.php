<?php

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Department;
use App\Models\Matter;
use App\Models\MatterType;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\PracticeDemoSeeder;

test('seeds five distinct clients and matters with four calendar events without duplicates', function () {
    $eventTitles = [
        'Solar project instructions meeting',
        'Supplier recovery strategy session',
        'Probate document review',
        'Employment disciplinary hearing',
    ];
    $department = Department::factory()->create();
    $attorneyPosition = Position::factory()->create([
        'department_id' => $department->id,
        'is_attorney' => true,
    ]);
    User::factory()->create([
        'department_id' => $department->id,
        'position_id' => $attorneyPosition->id,
        'status' => 'active',
    ]);
    foreach ([
        ['name' => 'Litigation', 'code' => 'litigation', 'short_code' => 'LIT'],
        ['name' => 'Corporate', 'code' => 'corporate', 'short_code' => 'COR'],
    ] as $matterType) {
        MatterType::query()->create([
            ...$matterType,
            'is_active' => true,
            'default_billing_type' => 'hourly',
        ]);
    }

    $this->seed(PracticeDemoSeeder::class);

    expect(Client::query()->whereHas('emailContact', fn ($contact) => $contact->where('normalized_value', 'like', 'practice-demo-%@zolomphi.example'))->count())->toBe(5);
    expect(Matter::query()->where('metadata->seed_source', 'practice_demo_2026')->count())->toBe(5);
    expect(Appointment::query()->whereIn('title', $eventTitles)->count())->toBe(4);

    $this->seed(PracticeDemoSeeder::class);

    expect(Client::query()->whereHas('emailContact', fn ($contact) => $contact->where('normalized_value', 'like', 'practice-demo-%@zolomphi.example'))->count())->toBe(5);
    expect(Matter::query()->where('metadata->seed_source', 'practice_demo_2026')->count())->toBe(5);
    expect(Appointment::query()->whereIn('title', $eventTitles)->count())->toBe(4);
});
