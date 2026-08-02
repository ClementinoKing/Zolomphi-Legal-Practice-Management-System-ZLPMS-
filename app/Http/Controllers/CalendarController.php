<?php

namespace App\Http\Controllers;

use App\Http\Requests\CalendarIndexRequest;
use App\Models\Appointment;
use App\Models\Matter;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function index(CalendarIndexRequest $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $month = Carbon::createFromFormat('Y-m', $request->validated('month', now()->format('Y-m')))->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        $appointments = Appointment::query()
            ->whereHas('matter', fn (Builder $matter): Builder => $matter->whereIn('id', Matter::query()->visibleTo($user)->select('id')))
            ->with(['matter:id,matter_number,title,client_id', 'matter.client:id,full_name,organization_name'])
            ->whereBetween('starts_at', [$month, $monthEnd])
            ->orderBy('starts_at')
            ->get()
            ->toBase()
            ->map(fn (Appointment $appointment): array => $this->appointmentEvent($appointment));

        $tasks = Task::query()
            ->whereHas('matter', fn (Builder $matter): Builder => $matter->whereIn('id', Matter::query()->visibleTo($user)->select('id')))
            ->with(['matter:id,matter_number,title,client_id', 'matter.client:id,full_name,organization_name'])
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$month->toDateString(), $monthEnd->toDateString()])
            ->orderBy('due_at')
            ->get()
            ->toBase()
            ->map(fn (Task $task): array => $this->taskEvent($task));

        $matterDeadlines = Matter::query()->visibleTo($user)
            ->with('client:id,full_name,organization_name')
            ->whereNotNull('deadline_at')
            ->whereBetween('deadline_at', [$month->toDateString(), $monthEnd->toDateString()])
            ->orderBy('deadline_at')
            ->get()
            ->toBase()
            ->map(fn (Matter $matter): array => $this->matterDeadlineEvent($matter));

        return Inertia::render('calendar/index', [
            'month' => $month->format('Y-m'),
            'events' => $appointments
                ->merge($tasks)
                ->merge($matterDeadlines)
                ->sortBy('starts_at')
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    private function appointmentEvent(Appointment $appointment): array
    {
        return [
            'id' => 'appointment-'.$appointment->id,
            'kind' => 'event',
            'title' => $appointment->title,
            'starts_at' => $appointment->starts_at->toIso8601String(),
            'ends_at' => $appointment->ends_at->toIso8601String(),
            'is_all_day' => $appointment->is_all_day,
            'type' => $appointment->type,
            'status' => $appointment->status,
            'matter_id' => $appointment->matter_id,
            'matter_number' => $appointment->matter?->matter_number,
            'matter_title' => $appointment->matter?->title,
            'client' => $appointment->matter?->client->full_name
                ?? $appointment->matter?->client->organization_name,
        ];
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    private function taskEvent(Task $task): array
    {
        return [
            'id' => 'task-'.$task->id,
            'kind' => 'task',
            'title' => $task->title,
            'starts_at' => $task->due_at?->startOfDay()->toIso8601String(),
            'ends_at' => null,
            'is_all_day' => true,
            'type' => 'deadline',
            'status' => $task->status,
            'matter_id' => $task->matter_id,
            'matter_number' => $task->matter?->matter_number,
            'matter_title' => $task->matter?->title,
            'client' => $task->matter?->client->full_name
                ?? $task->matter?->client->organization_name,
        ];
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    private function matterDeadlineEvent(Matter $matter): array
    {
        return [
            'id' => 'matter-deadline-'.$matter->id,
            'kind' => 'task',
            'title' => $matter->title,
            'starts_at' => $matter->deadline_at?->startOfDay()->toIso8601String(),
            'ends_at' => null,
            'is_all_day' => true,
            'type' => 'deadline',
            'status' => $matter->status,
            'matter_id' => $matter->id,
            'matter_number' => $matter->matter_number,
            'matter_title' => $matter->title,
            'client' => $matter->client->full_name
                ?? $matter->client->organization_name,
        ];
    }
}
