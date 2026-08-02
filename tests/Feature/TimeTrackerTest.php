<?php

use App\Models\ActivityLog;
use App\Models\Matter;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TimeEntry;
use App\Models\TimeTracker;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function timeTrackerUser(): User
{
    $permission = Permission::query()->firstOrCreate(
        ['key' => 'administration.access'],
        ['name' => 'Access administration', 'group' => 'Administration'],
    );
    $role = Role::query()->firstOrCreate(['name' => 'Time tracker user']);
    $role->assignedPermissions()->syncWithoutDetaching([$permission->id]);

    $user = User::factory()->create();
    $user->roles()->attach($role);

    return grantAllPracticePermissions($user);
}

test('users can start a personal matter timer and receive it as a shared prop', function () {
    $user = timeTrackerUser();
    $matter = Matter::factory()->create(['default_hourly_rate' => 60000]);

    $this->actingAs($user)
        ->postJson(route('time-tracker.start'), ['matter_id' => $matter->id])
        ->assertCreated()
        ->assertJsonPath('tracker.status', 'running')
        ->assertJsonPath('tracker.matter.id', $matter->id)
        ->assertJsonPath('tracker.matter.default_hourly_rate', '60000.00');

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('timeTracker.status', 'running')
            ->where('timeTracker.matter.id', $matter->id));

    $this->getJson(route('time-tracker.matters'))
        ->assertOk()
        ->assertJsonFragment(['id' => $matter->id]);

    expect(TimeTracker::query()->where('user_id', $user->id)->exists())->toBeTrue();
    expect(ActivityLog::query()->where('event', 'time-tracker.started')->exists())->toBeTrue();
});

test('paused time is excluded and only the timer owner can control the timer', function () {
    $user = timeTrackerUser();
    $matter = Matter::factory()->create();
    $tracker = $this->actingAs($user)
        ->postJson(route('time-tracker.start'), ['matter_id' => $matter->id])
        ->json('tracker');

    $this->travel(10)->seconds();
    $this->patchJson(route('time-tracker.pause', $tracker['id']))
        ->assertOk()
        ->assertJsonPath('tracker.status', 'paused')
        ->assertJsonPath('tracker.elapsed_seconds', 10);

    $this->travel(30)->seconds();
    $otherUser = timeTrackerUser();
    $this->actingAs($otherUser)
        ->patchJson(route('time-tracker.resume', $tracker['id']))
        ->assertForbidden();

    $this->actingAs($user)
        ->patchJson(route('time-tracker.resume', $tracker['id']))
        ->assertOk();
    $this->travel(5)->seconds();
    $this->patchJson(route('time-tracker.stop', $tracker['id']))
        ->assertOk()
        ->assertJsonPath('tracker.status', 'stopped')
        ->assertJsonPath('tracker.elapsed_seconds', 15);

    expect(ActivityLog::query()->where('event', 'time-tracker.paused')->exists())->toBeTrue();
    expect(ActivityLog::query()->where('event', 'time-tracker.resumed')->exists())->toBeTrue();
    expect(ActivityLog::query()->where('event', 'time-tracker.stopped')->exists())->toBeTrue();
});

test('a stopped timer saves a draft time entry rounded up to quarter hours', function () {
    $user = timeTrackerUser();
    $matter = Matter::factory()->create(['default_hourly_rate' => 60000]);
    $tracker = $this->actingAs($user)
        ->postJson(route('time-tracker.start'), ['matter_id' => $matter->id])
        ->json('tracker');

    $this->travel(901)->seconds();
    $this->patchJson(route('time-tracker.stop', $tracker['id']))->assertOk();

    $this->postJson(route('time-tracker.complete', $tracker['id']), [
        'entry_date' => now()->toDateString(),
        'description' => 'Reviewed the contract and prepared negotiation notes.',
        'hourly_rate' => 65000,
        'is_billable' => true,
    ])
        ->assertOk()
        ->assertJsonPath('tracker', null);

    $timeEntry = TimeEntry::query()->where('matter_id', $matter->id)->sole();

    expect($timeEntry->matter_id)->toBe($matter->id);
    expect($timeEntry->user_id)->toBe($user->id);
    expect($timeEntry->hours)->toBe('0.50');
    expect($timeEntry->hourly_rate)->toBe('65000.00');
    expect($timeEntry->is_billable)->toBeTrue();
    expect($timeEntry->status)->toBe('draft');
    expect(TimeTracker::query()->exists())->toBeFalse();
    expect(ActivityLog::query()->where('event', 'time-tracker.time-entry_saved')->exists())->toBeTrue();
});

test('users must resolve a stopped timer before starting another and can discard it', function () {
    $user = timeTrackerUser();
    $firstMatter = Matter::factory()->create();
    $secondMatter = Matter::factory()->create();
    $tracker = $this->actingAs($user)
        ->postJson(route('time-tracker.start'), ['matter_id' => $firstMatter->id])
        ->json('tracker');

    $this->patchJson(route('time-tracker.stop', $tracker['id']))->assertOk();
    $this->postJson(route('time-tracker.start'), ['matter_id' => $secondMatter->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('timer');

    $this->deleteJson(route('time-tracker.discard', $tracker['id']))
        ->assertOk()
        ->assertJsonPath('tracker', null);

    $this->postJson(route('time-tracker.start'), ['matter_id' => $secondMatter->id])
        ->assertCreated()
        ->assertJsonPath('tracker.matter.id', $secondMatter->id);

    expect(ActivityLog::query()->where('event', 'time-tracker.discarded')->exists())->toBeTrue();
});
