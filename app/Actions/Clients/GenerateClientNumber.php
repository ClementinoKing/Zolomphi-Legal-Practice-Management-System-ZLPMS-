<?php

namespace App\Actions\Clients;

use App\Models\Client;
use Illuminate\Support\Str;

class GenerateClientNumber
{
    public function handle(): string
    {
        $latestNumber = Client::query()
            ->withTrashed()
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('client_number');

        $lastSequence = (int) Str::afterLast($latestNumber ?? 'CLI-000000', '-');

        return sprintf('CLI-%06d', $lastSequence + 1);
    }
}
