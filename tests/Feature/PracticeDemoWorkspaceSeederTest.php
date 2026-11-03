<?php

use App\Models\Appointment;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Matter;
use App\Models\MatterCharge;
use App\Models\MatterEmail;
use App\Models\MatterNote;
use App\Models\Task;
use App\Models\TimeEntry;
use Database\Seeders\PracticeDemoSeeder;
use Database\Seeders\PracticeDemoWorkspaceSeeder;

test('populates every demo matter workspace without duplicating records', function () {
    $this->seed(PracticeDemoSeeder::class);
    $this->seed(PracticeDemoWorkspaceSeeder::class);

    $matterIds = Matter::query()
        ->where('metadata->seed_source', 'practice_demo_2026')
        ->pluck('id');

    expect($matterIds)->toHaveCount(5);
    expect(Task::query()->whereIn('matter_id', $matterIds)->count())->toBe(15);
    expect(TimeEntry::query()->whereIn('matter_id', $matterIds)->count())->toBe(10);
    expect(Document::query()->whereIn('matter_id', $matterIds)->count())->toBe(5);
    expect(MatterNote::query()->whereIn('matter_id', $matterIds)->count())->toBe(5);
    expect(MatterEmail::query()->whereIn('matter_id', $matterIds)->count())->toBe(5);
    expect(Appointment::query()->whereIn('matter_id', $matterIds)->where('title', 'Matter workspace progress review')->count())->toBe(5);
    expect(Expense::query()->whereIn('matter_id', $matterIds)->count())->toBe(5);
    expect(MatterCharge::query()->whereIn('matter_id', $matterIds)->count())->toBe(5);

    $this->seed(PracticeDemoWorkspaceSeeder::class);

    expect(Task::query()->whereIn('matter_id', $matterIds)->count())->toBe(15);
    expect(TimeEntry::query()->whereIn('matter_id', $matterIds)->count())->toBe(10);
    expect(Document::query()->whereIn('matter_id', $matterIds)->count())->toBe(5);
    expect(MatterNote::query()->whereIn('matter_id', $matterIds)->count())->toBe(5);
    expect(MatterEmail::query()->whereIn('matter_id', $matterIds)->count())->toBe(5);
    expect(Appointment::query()->whereIn('matter_id', $matterIds)->where('title', 'Matter workspace progress review')->count())->toBe(5);
    expect(Expense::query()->whereIn('matter_id', $matterIds)->count())->toBe(5);
    expect(MatterCharge::query()->whereIn('matter_id', $matterIds)->count())->toBe(5);
});
