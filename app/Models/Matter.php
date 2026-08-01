<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
    'closed_at',
    'closure_summary',
    'metadata',
])]
class Matter extends Model
{
    /** @use HasFactory<\Database\Factories\MatterFactory> */
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
        return $this->belongsTo(MatterType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_at' => 'date',
            'closed_at' => 'date',
            'default_hourly_rate' => 'decimal:2',
            'budget_amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }
}
