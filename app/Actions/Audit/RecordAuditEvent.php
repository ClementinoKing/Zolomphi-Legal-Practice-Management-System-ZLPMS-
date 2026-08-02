<?php

namespace App\Actions\Audit;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RecordAuditEvent
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function handle(
        ?User $user,
        string $event,
        string $description,
        ?Model $subject = null,
        ?int $clientId = null,
        ?int $matterId = null,
        array $properties = [],
    ): ActivityLog {
        return ActivityLog::query()->create([
            'user_id' => $user?->id,
            'client_id' => $clientId,
            'matter_id' => $matterId,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'event' => $event,
            'description' => $description,
            'properties' => $properties === [] ? null : $properties,
        ]);
    }
}
