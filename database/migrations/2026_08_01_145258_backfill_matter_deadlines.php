<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $baseDate = now()->startOfDay();

        DB::table('matters')
            ->whereNull('deadline_at')
            ->select(['id', 'priority'])
            ->orderBy('id')
            ->eachById(function (object $matter) use ($baseDate): void {
                $daysUntilDeadline = match ($matter->priority) {
                    'urgent' => 7 + ($matter->id % 7),
                    'high' => 14 + ($matter->id % 14),
                    'low' => 49 + ($matter->id % 21),
                    default => 28 + ($matter->id % 21),
                };

                DB::table('matters')
                    ->where('id', $matter->id)
                    ->update([
                        'deadline_at' => $baseDate->copy()->addDays($daysUntilDeadline)->toDateString(),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        // This data backfill is intentionally irreversible.
    }
};
