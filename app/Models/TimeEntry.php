<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $matter_id
 * @property int|null $user_id
 * @property Carbon $entry_date
 * @property string $description
 * @property string $hours
 * @property string|null $hourly_rate
 * @property bool $is_billable
 * @property string $status
 */
#[Fillable(['matter_id', 'user_id', 'entry_date', 'description', 'hours', 'hourly_rate', 'is_billable', 'status'])]
class TimeEntry extends Model
{
    protected $attributes = ['is_billable' => true, 'status' => 'draft'];

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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasOne<InvoiceItem, $this>
     */
    public function invoiceItem(): HasOne
    {
        return $this->hasOne(InvoiceItem::class);
    }

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'hours' => 'decimal:2', 'hourly_rate' => 'decimal:2', 'is_billable' => 'boolean'];
    }
}
