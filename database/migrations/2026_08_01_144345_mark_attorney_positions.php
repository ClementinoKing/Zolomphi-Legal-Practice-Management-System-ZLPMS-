<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $attorneyCodes = [
            'managing-partner',
            'equity-partner',
            'head-of-litigation',
            'senior-associate-litigation',
            'associate-litigation',
            'head-of-corporate-commercial',
            'senior-associate-corporate',
            'associate-corporate',
            'head-of-conveyancing',
            'conveyancing-associate',
        ];

        DB::table('positions')
            ->whereIn('code', $attorneyCodes)
            ->update(['is_attorney' => true]);

        DB::table('matters')
            ->join('users', 'users.id', '=', 'matters.responsible_user_id')
            ->join('positions', 'positions.id', '=', 'users.position_id')
            ->where('positions.is_attorney', true)
            ->select('matters.id as matter_id', 'users.id as user_id')
            ->orderBy('matters.id')
            ->chunkById(100, function ($assignments): void {
                foreach ($assignments as $assignment) {
                    DB::table('matter_attorney')->updateOrInsert(
                        ['matter_id' => $assignment->matter_id, 'user_id' => $assignment->user_id],
                        ['assigned_at' => now()],
                    );
                }
            }, 'matters.id', 'matter_id');
    }

    public function down(): void
    {
        DB::table('positions')
            ->whereIn('code', [
                'managing-partner',
                'equity-partner',
                'head-of-litigation',
                'senior-associate-litigation',
                'associate-litigation',
                'head-of-corporate-commercial',
                'senior-associate-corporate',
                'associate-corporate',
                'head-of-conveyancing',
                'conveyancing-associate',
            ])
            ->update(['is_attorney' => false]);
    }
};
