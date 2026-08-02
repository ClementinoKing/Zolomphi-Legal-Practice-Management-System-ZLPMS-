<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuditLogIndexRequest;
use App\Models\ActivityLog;
use App\Models\Matter;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(AuditLogIndexRequest $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $filters = $request->validated();
        $logs = ActivityLog::query()
            ->where(function (Builder $query) use ($user): void {
                $query->whereNull('matter_id')
                    ->orWhereHas('matter', fn (Builder $matter): Builder => $matter->whereIn('id', Matter::query()->visibleTo($user)->select('id')));
            })
            ->with(['user:id,name', 'matter:id,matter_number,title', 'client:id,full_name,organization_name'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('event', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['event'] ?? null, fn (Builder $query, string $event): Builder => $query->where('event', $event))
            ->when($filters['start_date'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
            ->when($filters['end_date'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ActivityLog $activity): array => [
                'id' => $activity->id,
                'event' => $activity->event,
                'description' => $activity->description,
                'actor' => $activity->user->name,
                'client' => $activity->client->organization_name ?? $activity->client->full_name,
                'matter' => $activity->matter?->matter_number,
                'matter_id' => $activity->matter_id,
                'created_at' => $activity->created_at->toIso8601String(),
            ]);

        return Inertia::render('audit-logs/index', [
            'logs' => $logs,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'event' => $filters['event'] ?? '',
                'start_date' => $filters['start_date'] ?? '',
                'end_date' => $filters['end_date'] ?? '',
            ],
            'events' => ActivityLog::query()->distinct()->orderBy('event')->pluck('event')->all(),
        ]);
    }
}
