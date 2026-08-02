<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $matter_id
 * @property int|null $client_id
 * @property string $event
 * @property string|null $description
 * @property Carbon $created_at
 * @property-read User|null $user
 * @property-read Matter|null $matter
 * @property-read Client|null $client
 */
#[Fillable(['user_id', 'matter_id', 'client_id', 'subject_type', 'subject_id', 'event', 'description', 'properties', 'ip_address', 'user_agent'])]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault(['name' => 'System']);
    }

    /**
     * @return BelongsTo<Matter, $this>
     */
    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withDefault(['full_name' => 'Firm']);
    }

    protected function casts(): array
    {
        return ['properties' => 'array', 'created_at' => 'datetime'];
    }
}
