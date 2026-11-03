<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string|null $short_code
 */
#[Fillable(['name', 'code', 'short_code', 'description', 'default_billing_type', 'default_hourly_rate', 'is_active', 'sort_order'])]
class MatterType extends Model
{
    use SoftDeletes;

    /**
     * @return HasMany<Matter, $this>
     */
    public function matters(): HasMany
    {
        return $this->hasMany(Matter::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_hourly_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
