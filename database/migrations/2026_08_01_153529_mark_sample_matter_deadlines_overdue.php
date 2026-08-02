<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $overdueDates = [
            now()->subDays(2),
            now()->subDays(5),
            now()->subDays(8),
            now()->subDays(11),
            now()->subDays(14),
        ];

        DB::table('matters')
            ->whereJsonContains('metadata->source', 'practice-sample-data')
            ->orderBy('opened_at')
            ->limit(5)
            ->get(['id'])
            ->each(function (object $matter, int $index) use ($overdueDates): void {
                DB::table('matters')
                    ->where('id', $matter->id)
                    ->update([
                        'deadline_at' => $overdueDates[$index]->toDateString(),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        // This demonstration-data change is intentionally irreversible.
    }
};
