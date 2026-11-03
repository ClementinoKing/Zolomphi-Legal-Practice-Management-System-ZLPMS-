<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'client_id',
    'candidate_client_id',
    'decision',
    'matched_fields',
    'reviewed_by_user_id',
    'reviewed_at',
])]
class ClientDuplicateReview extends Model
{
    protected function casts(): array
    {
        return [
            'matched_fields' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }
}
