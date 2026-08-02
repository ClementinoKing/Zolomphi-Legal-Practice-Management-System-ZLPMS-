<?php

use App\Models\InternalTask;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('users can assign internal tasks and recipients see them in their assigned view', function () {
    $creator = User::factory()->create(['status' => 'active']);
    $recipient = User::factory()->create(['status' => 'active']);

    $this->actingAs($creator)
        ->get(route('internal-tasks.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('internal-tasks/index')
            ->where('stats.open', 0)
            ->has('users'));

    $response = $this->actingAs($creator)->post(route('internal-tasks.store'), [
        'title' => 'Prepare internal handover',
        'description' => 'Share the current status and next steps.',
        'priority' => 'high',
        'due_at' => '2026-08-08',
        'assignee_ids' => [$recipient->id],
    ]);

    $task = InternalTask::query()->firstOrFail();

    $response->assertRedirect(route('internal-tasks.index', ['view' => 'created']));
    expect($task->title)->toBe('Prepare internal handover')
        ->and($task->assignees()->pluck('users.id')->all())->toBe([$recipient->id]);
    expect($recipient->notifications()->count())->toBe(1);
    expect($recipient->notifications()->first()->data['body'])->toContain('Due 8 Aug 2026');

    $this->actingAs($recipient)
        ->get(route('internal-tasks.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('internal-tasks/index')
            ->has('tasks.data', 1)
            ->where('tasks.data.0.title', 'Prepare internal handover')
            ->where('tasks.data.0.can_edit', false)
            ->where('tasks.data.0.can_complete', true));

    $this->actingAs($recipient)
        ->patch(route('internal-tasks.update', $task), [
            'title' => 'Attempted edit',
            'priority' => 'normal',
            'assignee_ids' => [$creator->id],
        ])
        ->assertForbidden();

    $this->actingAs($recipient)
        ->patch(route('internal-tasks.complete', $task))
        ->assertRedirect(route('internal-tasks.index'));

    expect($task->refresh()->status)->toBe('completed')
        ->and($task->completed_at)->not->toBeNull();
    expect($creator->notifications()->count())->toBe(1);
});

test('users cannot assign an internal task to themselves', function () {
    $user = User::factory()->create(['status' => 'active']);

    $this->actingAs($user)
        ->post(route('internal-tasks.store'), [
            'title' => 'Self assignment',
            'priority' => 'normal',
            'assignee_ids' => [$user->id],
        ])
        ->assertSessionHasErrors('assignee_ids.0');
});
