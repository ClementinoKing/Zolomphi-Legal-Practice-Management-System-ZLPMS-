<?php

namespace App\Models;

use Database\Factories\MatterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $matter_number
 * @property int $client_id
 * @property int|null $matter_type_id
 * @property int|null $responsible_user_id
 * @property string $title
 * @property string $status
 * @property string $priority
 * @property string $billing_type
 * @property string|null $budget_amount
 * @property Carbon $opened_at
 * @property Carbon|null $deadline_at
 * @property-read Client $client
 * @property-read MatterType $matterType
 * @property-read User $responsibleUser
 * @property-read int $participants_count
 * @property-read int $total
 */
#[Fillable([
    'matter_number',
    'client_id',
    'matter_type_id',
    'responsible_user_id',
    'opened_by_user_id',
    'title',
    'description',
    'status',
    'priority',
    'billing_type',
    'default_hourly_rate',
    'budget_amount',
    'opened_at',
    'deadline_at',
    'closed_at',
    'closure_summary',
    'metadata',
])]
class Matter extends Model
{
    /** @use HasFactory<MatterFactory> */
    use HasFactory, SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'open',
        'priority' => 'normal',
        'billing_type' => 'hourly',
    ];

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<MatterType, $this>
     */
    public function matterType(): BelongsTo
    {
        return $this->belongsTo(MatterType::class)->withDefault([
            'name' => 'Unclassified',
        ]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id')->withDefault([
            'name' => 'Unassigned',
        ]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function openedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    /**
     * @return HasMany<MatterParty, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(MatterParty::class);
    }

    /**
     * Internal legal practitioners assigned to work on the matter.
     *
     * @return BelongsToMany<User, $this>
     */
    public function attorneys(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'matter_attorney')
            ->withPivot('assigned_at');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return HasMany<TimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /**
     * @return HasMany<TimeTracker, $this>
     */
    public function timeTrackers(): HasMany
    {
        return $this->hasMany(TimeTracker::class);
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * @return HasMany<MatterNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(MatterNote::class);
    }

    /**
     * @return HasMany<MatterEmail, $this>
     */
    public function emails(): HasMany
    {
        return $this->hasMany(MatterEmail::class);
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * @return HasMany<MatterCharge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(MatterCharge::class);
    }

    /**
     * Invoices raised specifically for this matter.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Restrict matters to records visible to a user according to the catalog.
     *
     * @param  Builder<Matter>  $query
     * @return Builder<Matter>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasPermission('matters.view.all')) {
            return $query;
        }

        if (! $user->hasAnyPermission([
            'matters.view.assigned',
            'matters.view.department',
        ])) {
            return $query->whereKey(-1);
        }

        return $query->where(function (Builder $visible) use ($user): void {
            if ($user->hasPermission('matters.view.assigned')) {
                $visible
                    ->where('responsible_user_id', $user->id)
                    ->orWhereHas('attorneys', fn (Builder $attorneys): Builder => $attorneys->whereKey($user->id));
            }

            if ($user->hasPermission('matters.view.department') && $user->department_id !== null) {
                $visible->orWhereHas('responsibleUser', fn (Builder $responsible): Builder => $responsible
                    ->where('department_id', $user->department_id));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_at' => 'date',
            'deadline_at' => 'date',
            'closed_at' => 'date',
            'default_hourly_rate' => 'decimal:2',
            'budget_amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }
}
