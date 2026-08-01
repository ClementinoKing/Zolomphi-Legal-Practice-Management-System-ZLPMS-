<?php

namespace App\Actions\Matters;

use App\Models\Matter;
use Illuminate\Support\Str;

class GenerateMatterNumber
{
    public function handle(): string
    {
        $latestNumber = Matter::query()
            ->withTrashed()
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('matter_number');

        $lastSequence = (int) Str::afterLast($latestNumber ?? 'MAT-000000', '-');

        return sprintf('MAT-%06d', $lastSequence + 1);
    }
}
