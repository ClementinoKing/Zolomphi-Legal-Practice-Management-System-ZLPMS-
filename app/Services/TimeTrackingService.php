<?php

namespace App\Services;

use App\Actions\Audit\RecordAuditEvent;
use App\Models\Matter;
use App\Models\TimeEntry;
use App\Models\TimeTracker;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimeTrackingService
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function start(User $user, Matter $matter): TimeTracker
    {
        return DB::transaction(function () use ($user, $matter): TimeTracker {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if (TimeTracker::query()->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages([
                    'timer' => 'Save or discard your existing timer before starting another one.',
                ]);
            }

            $now = now();
            $tracker = TimeTracker::query()->create([
                'user_id' => $user->id,
                'matter_id' => $matter->id,
                'status' => 'running',
                'started_at' => $now,
                'last_resumed_at' => $now,
            ]);

            $this->record($tracker, $user, 'time-tracker.started', 'Started a timer for '.$matter->matter_number.'.');

            return $tracker->load('matter.client');
        });
    }

    public function pause(TimeTracker $timeTracker, User $user): TimeTracker
    {
        return DB::transaction(function () use ($timeTracker, $user): TimeTracker {
            $tracker = $this->lockedTracker($timeTracker, $user);
            $this->requireStatus($tracker, 'running', 'Only a running timer can be paused.');
            $now = now();

            $tracker->forceFill([
                'status' => 'paused',
                'accumulated_seconds' => $tracker->elapsedSeconds($now),
                'last_resumed_at' => null,
                'paused_at' => $now,
            ])->save();

            $this->record($tracker, $user, 'time-tracker.paused', 'Paused a timer for '.$tracker->matter->matter_number.'.');

            return $tracker->load('matter.client');
        });
    }

    public function resume(TimeTracker $timeTracker, User $user): TimeTracker
    {
        return DB::transaction(function () use ($timeTracker, $user): TimeTracker {
            $tracker = $this->lockedTracker($timeTracker, $user);
            $this->requireStatus($tracker, 'paused', 'Only a paused timer can be resumed.');
            $now = now();

            $tracker->forceFill([
                'status' => 'running',
                'last_resumed_at' => $now,
                'paused_at' => null,
            ])->save();

            $this->record($tracker, $user, 'time-tracker.resumed', 'Resumed a timer for '.$tracker->matter->matter_number.'.');

            return $tracker->load('matter.client');
        });
    }

    public function stop(TimeTracker $timeTracker, User $user): TimeTracker
    {
        return DB::transaction(function () use ($timeTracker, $user): TimeTracker {
            $tracker = $this->lockedTracker($timeTracker, $user);

            if (! in_array($tracker->status, ['running', 'paused'], true)) {
                throw ValidationException::withMessages([
                    'timer' => 'Only a running or paused timer can be stopped.',
                ]);
            }

            $now = now();
            $tracker->forceFill([
                'status' => 'stopped',
                'accumulated_seconds' => $tracker->elapsedSeconds($now),
                'last_resumed_at' => null,
                'paused_at' => null,
                'stopped_at' => $now,
            ])->save();

            $this->record($tracker, $user, 'time-tracker.stopped', 'Stopped a timer for '.$tracker->matter->matter_number.'.');

            return $tracker->load('matter.client');
        });
    }

    /**
     * @param  array{entry_date: string, description: string, hourly_rate?: float|int|string|null, is_billable: bool}  $attributes
     */
    public function complete(TimeTracker $timeTracker, User $user, array $attributes): TimeEntry
    {
        return DB::transaction(function () use ($timeTracker, $user, $attributes): TimeEntry {
            $tracker = $this->lockedTracker($timeTracker, $user);
            $this->requireStatus($tracker, 'stopped', 'Stop the timer before saving a time entry.');
            $matter = Matter::query()->findOrFail($tracker->matter_id);
            $hours = $this->roundedHours($tracker->accumulated_seconds);

            $timeEntry = $matter->timeEntries()->create([
                'user_id' => $user->id,
                'entry_date' => $attributes['entry_date'],
                'description' => $attributes['description'],
                'hours' => $hours,
                'hourly_rate' => $attributes['hourly_rate'] ?? $matter->default_hourly_rate,
                'is_billable' => $attributes['is_billable'],
                'status' => 'draft',
            ]);

            $this->audit->handle(
                user: $user,
                event: 'time-tracker.time-entry_saved',
                description: 'Saved timed work for '.$matter->matter_number.'.',
                subject: $timeEntry,
                clientId: $matter->client_id,
                matterId: $matter->id,
                properties: [
                    'duration_seconds' => $tracker->accumulated_seconds,
                    'hours' => $hours,
                ],
            );

            $tracker->delete();

            return $timeEntry;
        });
    }

    public function discard(TimeTracker $timeTracker, User $user): void
    {
        DB::transaction(function () use ($timeTracker, $user): void {
            $tracker = $this->lockedTracker($timeTracker, $user);
            $this->requireStatus($tracker, 'stopped', 'Only a stopped timer can be discarded.');
            $this->record($tracker, $user, 'time-tracker.discarded', 'Discarded stopped timed work for '.$tracker->matter->matter_number.'.');
            $tracker->delete();
        });
    }

    private function lockedTracker(TimeTracker $timeTracker, User $user): TimeTracker
    {
        $tracker = TimeTracker::query()
            ->whereKey($timeTracker->id)
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->first();

        if ($tracker === null) {
            throw new AuthorizationException;
        }

        return $tracker->load('matter.client');
    }

    private function requireStatus(TimeTracker $tracker, string $status, string $message): void
    {
        if ($tracker->status !== $status) {
            throw ValidationException::withMessages(['timer' => $message]);
        }
    }

    private function roundedHours(int $seconds): float
    {
        return ceil(max(1, $seconds) / 900) * 0.25;
    }

    private function record(TimeTracker $tracker, User $user, string $event, string $description): void
    {
        $this->audit->handle(
            user: $user,
            event: $event,
            description: $description,
            subject: $tracker,
            clientId: $tracker->matter->client_id,
            matterId: $tracker->matter_id,
        );
    }
}
