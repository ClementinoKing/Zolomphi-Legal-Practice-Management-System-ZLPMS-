<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $due_at
 * @property Carbon|null $completed_at
 * @property-read int $total
 */
#[Fillable(['matter_id', 'created_by_user_id', 'title', 'description', 'status', 'priority', 'due_at', 'completed_at'])]
class Task extends Model
{
    protected $attributes = ['status' => 'pending', 'priority' => 'normal'];

    /**
     * @return BelongsTo<Matter, $this>
     */
    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('assigned_at');
    }

    protected function casts(): array
    {
        return ['due_at' => 'date', 'completed_at' => 'datetime'];
    }
}
