<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteTimeTrackerRequest;
use App\Http\Requests\StartTimeTrackerRequest;
use App\Http\Resources\TimeTrackerResource;
use App\Models\Matter;
use App\Models\TimeTracker;
use App\Models\User;
use App\Services\TimeTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimeTrackerController extends Controller
{
    public function __construct(private readonly TimeTrackingService $timeTracking) {}

    public function matters(): JsonResponse
    {
        return response()->json([
            'matters' => Matter::query()
                ->with('client:id,full_name,organization_name')
                ->orderBy('title')
                ->get(['id', 'client_id', 'matter_number', 'title', 'default_hourly_rate'])
                ->map(fn (Matter $matter): array => [
                    'id' => $matter->id,
                    'matter_number' => $matter->matter_number,
                    'title' => $matter->title,
                    'client_name' => $matter->client->full_name ?? $matter->client->organization_name ?? 'Unnamed client',
                    'default_hourly_rate' => $matter->default_hourly_rate,
                ])->all(),
        ]);
    }

    public function start(StartTimeTrackerRequest $request): JsonResponse
    {
        $matter = Matter::query()->findOrFail($request->integer('matter_id'));
        $tracker = $this->timeTracking->start($this->user($request), $matter);

        return $this->trackerResponse($tracker, 201);
    }

    public function pause(Request $request, TimeTracker $timeTracker): JsonResponse
    {
        return $this->trackerResponse($this->timeTracking->pause($timeTracker, $this->user($request)));
    }

    public function resume(Request $request, TimeTracker $timeTracker): JsonResponse
    {
        return $this->trackerResponse($this->timeTracking->resume($timeTracker, $this->user($request)));
    }

    public function stop(Request $request, TimeTracker $timeTracker): JsonResponse
    {
        return $this->trackerResponse($this->timeTracking->stop($timeTracker, $this->user($request)));
    }

    public function complete(CompleteTimeTrackerRequest $request, TimeTracker $timeTracker): JsonResponse
    {
        $timeEntry = $this->timeTracking->complete($timeTracker, $this->user($request), $request->validated());

        return response()->json([
            'tracker' => null,
            'time_entry' => ['id' => $timeEntry->id],
            'server_now' => now()->toIso8601String(),
        ]);
    }

    public function discard(Request $request, TimeTracker $timeTracker): JsonResponse
    {
        $this->timeTracking->discard($timeTracker, $this->user($request));

        return response()->json([
            'tracker' => null,
            'server_now' => now()->toIso8601String(),
        ]);
    }

    private function trackerResponse(TimeTracker $tracker, int $status = 200): JsonResponse
    {
        return response()->json([
            'tracker' => TimeTrackerResource::make($tracker)->resolve(),
            'server_now' => now()->toIso8601String(),
        ], $status);
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
