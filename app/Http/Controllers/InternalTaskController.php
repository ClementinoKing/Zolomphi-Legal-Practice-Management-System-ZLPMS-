<?php

namespace App\Http\Controllers;

use App\Actions\Notifications\SendPracticeAlert;
use App\Http\Requests\InternalTaskRequest;
use App\Models\InternalTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class InternalTaskController extends Controller
{
    public function __construct(private readonly SendPracticeAlert $alerts) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $view = $request->string('view')->value();
        $view = in_array($view, ['assigned', 'created', 'all'], true) ? $view : 'assigned';
        $status = $request->string('status')->value();
        $search = trim($request->string('search')->value());

        $visible = InternalTask::query()->visibleTo($user);

        if ($view === 'assigned') {
            $visible->whereHas('assignees', fn (Builder $query): Builder => $query->whereKey($user->id));
        } elseif ($view === 'created') {
            $visible->where('created_by_user_id', $user->id);
        }

        if ($status !== '' && in_array($status, ['pending', 'in_progress', 'completed', 'cancelled'], true)) {
            $visible->where('status', $status);
        }

        if ($search !== '') {
            $visible->where(function (Builder $query) use ($search): void {
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        $tasks = $visible
            ->with(['creator:id,name', 'assignees:id,name'])
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (InternalTask $task): array => $this->taskPayload($task, $user));

        $statsQuery = InternalTask::query()->visibleTo($user);

        return Inertia::render('internal-tasks/index', [
            'tasks' => $tasks,
            'users' => User::query()
                ->where('status', 'active')
                ->whereKeyNot($user->id)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'filters' => [
                'view' => $view,
                'status' => $status,
                'search' => $search,
            ],
            'openTaskId' => $request->integer('task_id') ?: null,
            'stats' => [
                'open' => (clone $statsQuery)->whereIn('status', ['pending', 'in_progress'])->count(),
                'due_soon' => (clone $statsQuery)->whereIn('status', ['pending', 'in_progress'])
                    ->whereNotNull('due_at')
                    ->whereBetween('due_at', [today(), today()->addDays(7)])
                    ->count(),
                'overdue' => (clone $statsQuery)->whereIn('status', ['pending', 'in_progress'])
                    ->whereDate('due_at', '<', today())
                    ->count(),
                'completed' => (clone $statsQuery)->where('status', 'completed')->count(),
            ],
        ]);
    }

    public function store(InternalTaskRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $assigneeIds = $this->assigneeIds($data['assignee_ids'] ?? []);
        $task = InternalTask::query()->create([
            ...Arr::except($data, ['assignee_ids']),
            'created_by_user_id' => $request->user()->id,
        ]);
        $task->assignees()->sync($assigneeIds);

        $this->notifyAssignees($task, $request->user(), $assigneeIds);

        return to_route('internal-tasks.index', ['view' => 'created'])
            ->with('success', 'Task assigned successfully.');
    }

    public function update(InternalTaskRequest $request, InternalTask $internalTask): RedirectResponse
    {
        $this->ensureCreator($internalTask, $request->user());
        $data = $request->validated();
        $attributes = Arr::except($data, ['assignee_ids']);

        if (($attributes['status'] ?? null) === 'completed' && $internalTask->completed_at === null) {
            $attributes['completed_at'] = now();
        } elseif (($attributes['status'] ?? null) !== null && $attributes['status'] !== 'completed') {
            $attributes['completed_at'] = null;
        }

        $internalTask->update($attributes);

        if (array_key_exists('assignee_ids', $data)) {
            $assigneeIds = $this->assigneeIds($data['assignee_ids']);
            $sync = $internalTask->assignees()->sync($assigneeIds);
            $this->notifyAssignees($internalTask, $request->user(), $sync['attached']);
        }

        return to_route('internal-tasks.index', ['view' => 'created'])
            ->with('success', 'Task updated successfully.');
    }

    public function complete(Request $request, InternalTask $internalTask): RedirectResponse
    {
        $this->ensureVisible($internalTask, $request->user());

        $internalTask->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        if ($internalTask->creator !== null) {
            $this->alerts->handle(
                collect([$internalTask->creator]),
                $request->user(),
                [
                    'title' => 'Task completed',
                    'body' => $request->user()->name.' completed “'.$internalTask->title.'”.',
                    'url' => route('internal-tasks.index', ['view' => 'created', 'task_id' => $internalTask->id]),
                    'type' => 'task',
                ],
            );
        }

        return to_route('internal-tasks.index')->with('success', 'Task marked as completed.');
    }

    /** @param array<int, mixed> $ids */
    private function assigneeIds(array $ids): array
    {
        return array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, $ids)));
    }

    /** @param array<int, int> $assigneeIds */
    private function notifyAssignees(InternalTask $task, User $actor, array $assigneeIds): void
    {
        if ($assigneeIds === []) {
            return;
        }

        $task->loadMissing('creator');
        $dueText = $task->due_at?->format('j M Y');
        $body = $actor->name.' assigned you “'.$task->title.'”.';

        if ($dueText !== null) {
            $body .= ' Due '.$dueText.'.';
        }

        $this->alerts->handle(
            User::query()->whereKey($assigneeIds)->get(),
            $actor,
            [
                'title' => 'New internal task assigned',
                'body' => $body,
                'url' => route('internal-tasks.index', ['view' => 'assigned', 'task_id' => $task->id]),
                'type' => 'task',
            ],
        );
    }

    private function ensureCreator(InternalTask $task, User $user): void
    {
        abort_unless($task->created_by_user_id === $user->id, 403);
    }

    private function ensureVisible(InternalTask $task, User $user): void
    {
        abort_unless(
            $task->created_by_user_id === $user->id || $task->assignees()->whereKey($user->id)->exists(),
            403,
        );
    }

    /** @return array<string, mixed> */
    private function taskPayload(InternalTask $task, User $user): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'priority' => $task->priority,
            'due_at' => $task->due_at?->toDateString(),
            'completed_at' => $task->completed_at?->toIso8601String(),
            'created_at' => $task->created_at?->toIso8601String(),
            'creator' => $task->creator?->only(['id', 'name']),
            'assignees' => $task->assignees->map(fn (User $assignee): array => $assignee->only(['id', 'name']))->values(),
            'can_edit' => $task->created_by_user_id === $user->id,
            'can_complete' => in_array($task->status, ['pending', 'in_progress'], true)
                && ($task->created_by_user_id === $user->id || $task->assignees->contains('id', $user->id)),
        ];
    }
}
