<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\TimeTrackerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $matter_id
 * @property string $status
 * @property CarbonInterface $started_at
 * @property CarbonInterface|null $last_resumed_at
 * @property CarbonInterface|null $paused_at
 * @property CarbonInterface|null $stopped_at
 * @property int $accumulated_seconds
 * @property-read Matter $matter
 * @property-read User $user
 */
#[Fillable([
    'user_id',
    'matter_id',
    'status',
    'started_at',
    'last_resumed_at',
    'paused_at',
    'stopped_at',
    'accumulated_seconds',
])]
class TimeTracker extends Model
{
    /** @use HasFactory<TimeTrackerFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Matter, $this>
     */
    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    public function elapsedSeconds(?CarbonInterface $at = null): int
    {
        if ($this->status !== 'running' || $this->last_resumed_at === null) {
            return $this->accumulated_seconds;
        }

        $at ??= now();

        return $this->accumulated_seconds + max(0, $this->last_resumed_at->diffInSeconds($at));
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_resumed_at' => 'datetime',
            'paused_at' => 'datetime',
            'stopped_at' => 'datetime',
            'accumulated_seconds' => 'integer',
        ];
    }
}
