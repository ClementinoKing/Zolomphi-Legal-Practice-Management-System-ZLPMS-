<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $timestamp = now();
        $departmentId = DB::table('departments')->where('code', 'ict')->value('id');

        if ($departmentId === null) {
            $departmentId = DB::table('departments')->insertGetId([
                'name' => 'Information and Communications Technology',
                'code' => 'ict',
                'description' => 'Technology operations, systems, information security, and user support.',
                'is_active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        $positions = [
            ['name' => 'ICT Manager', 'code' => 'ict-manager', 'description' => 'Leads the firm technology strategy, systems, and ICT service delivery.'],
            ['name' => 'Systems Administrator', 'code' => 'systems-administrator', 'description' => 'Maintains firm infrastructure, access controls, and core systems.'],
            ['name' => 'Software Developer', 'code' => 'software-developer', 'description' => 'Builds and maintains internal legal-practice applications and integrations.'],
            ['name' => 'ICT Support Officer', 'code' => 'ict-support-officer', 'description' => 'Provides end-user support, device management, and technical troubleshooting.'],
            ['name' => 'Information Security Officer', 'code' => 'information-security-officer', 'description' => 'Supports information security, privacy, and technology-risk controls.'],
        ];

        foreach ($positions as $position) {
            if (DB::table('positions')->where('code', $position['code'])->exists()) {
                continue;
            }

            DB::table('positions')->insert([
                ...$position,
                'department_id' => $departmentId,
                'is_active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }
    }

    public function down(): void
    {
    }
};
