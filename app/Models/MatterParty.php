<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'matter_id',
    'role',
    'type',
    'name',
    'organization_name',
    'contact_person',
    'email',
    'phone',
    'legal_representative',
    'address',
    'notes',
])]
class MatterParty extends Model
{
    /**
     * @return BelongsTo<Matter, $this>
     */
    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }
}
