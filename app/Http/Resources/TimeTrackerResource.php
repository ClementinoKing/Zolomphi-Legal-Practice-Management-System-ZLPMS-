<?php

namespace App\Http\Resources;

use App\Models\GeneralSetting;
use App\Models\TimeTracker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TimeTracker */
class TimeTrackerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TimeTracker $tracker */
        $tracker = $this->resource;
        $matter = $tracker->matter;
        $timezone = GeneralSetting::query()->value('timezone');

        return [
            'id' => $tracker->id,
            'status' => $tracker->status,
            'elapsed_seconds' => $tracker->elapsedSeconds(),
            'started_at' => $tracker->started_at->toIso8601String(),
            'last_resumed_at' => $tracker->last_resumed_at?->toIso8601String(),
            'paused_at' => $tracker->paused_at?->toIso8601String(),
            'stopped_at' => $tracker->stopped_at?->toIso8601String(),
            'entry_date' => $tracker->started_at
                ->setTimezone(is_string($timezone) ? $timezone : config('app.timezone'))
                ->toDateString(),
            'matter' => [
                'id' => $matter->id,
                'matter_number' => $matter->matter_number,
                'title' => $matter->title,
                'client_name' => $matter->client->full_name ?? $matter->client->organization_name ?? 'Unnamed client',
                'default_hourly_rate' => $matter->default_hourly_rate,
            ],
        ];
    }
}
