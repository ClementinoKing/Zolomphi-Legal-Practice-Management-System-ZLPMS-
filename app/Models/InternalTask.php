<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $due_at
 * @property Carbon|null $completed_at
 */
#[Fillable(['created_by_user_id', 'title', 'description', 'status', 'priority', 'due_at', 'completed_at'])]
class InternalTask extends Model
{
    use SoftDeletes;

    protected $attributes = [
        'status' => 'pending',
        'priority' => 'normal',
    ];

    /**
     * Limit tasks to those created by or assigned to the given user.
     *
     * @param  Builder<InternalTask>  $query
     * @return Builder<InternalTask>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user): void {
            $query
                ->where('created_by_user_id', $user->id)
                ->orWhereHas('assignees', fn (Builder $assignees): Builder => $assignees->whereKey($user->id));
        });
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
        return $this->belongsToMany(User::class, 'internal_task_user')->withPivot('assigned_at');
    }

    protected function casts(): array
    {
        return [
            'due_at' => 'date',
            'completed_at' => 'datetime',
        ];
    }
}
