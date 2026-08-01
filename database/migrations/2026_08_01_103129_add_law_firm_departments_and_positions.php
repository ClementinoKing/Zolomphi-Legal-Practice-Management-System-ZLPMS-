<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $timestamp = now();
        $departments = [
            [
                'name' => 'Management & Partnerships',
                'code' => 'management-partnerships',
                'description' => 'Firm leadership, governance, and partner management.',
            ],
            [
                'name' => 'Litigation & Dispute Resolution',
                'code' => 'litigation-dispute-resolution',
                'description' => 'Court, arbitration, mediation, and dispute-resolution matters.',
            ],
            [
                'name' => 'Corporate & Commercial',
                'code' => 'corporate-commercial',
                'description' => 'Corporate advisory, commercial transactions, and compliance work.',
            ],
            [
                'name' => 'Conveyancing & Property',
                'code' => 'conveyancing-property',
                'description' => 'Property transfers, registrations, and conveyancing transactions.',
            ],
            [
                'name' => 'Finance & Administration',
                'code' => 'finance-administration',
                'description' => 'Finance, people operations, office administration, and practice support.',
            ],
            [
                'name' => 'Client Services & Business Development',
                'code' => 'client-services-business-development',
                'description' => 'Client relationships, business development, and firm communications.',
            ],
        ];

        $departmentIds = [];

        foreach ($departments as $department) {
            $departmentId = DB::table('departments')
                ->where('code', $department['code'])
                ->value('id');

            if ($departmentId === null) {
                $departmentId = DB::table('departments')->insertGetId([
                    ...$department,
                    'is_active' => true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }

            $departmentIds[$department['code']] = $departmentId;
        }

        $positions = [
            ['department_code' => 'management-partnerships', 'name' => 'Managing Partner', 'code' => 'managing-partner', 'description' => 'Provides overall strategic and operational leadership for the firm.'],
            ['department_code' => 'management-partnerships', 'name' => 'Equity Partner', 'code' => 'equity-partner', 'description' => 'Leads client work and contributes to firm ownership and governance.'],
            ['department_code' => 'management-partnerships', 'name' => 'Practice Manager', 'code' => 'practice-manager', 'description' => 'Oversees day-to-day practice operations and service delivery.'],
            ['department_code' => 'litigation-dispute-resolution', 'name' => 'Head of Litigation', 'code' => 'head-of-litigation', 'description' => 'Leads litigation and dispute-resolution practice work.'],
            ['department_code' => 'litigation-dispute-resolution', 'name' => 'Senior Associate', 'code' => 'senior-associate-litigation', 'description' => 'Manages complex matters and supervises junior legal staff.'],
            ['department_code' => 'litigation-dispute-resolution', 'name' => 'Associate', 'code' => 'associate-litigation', 'description' => 'Conducts legal research, drafting, and case preparation.'],
            ['department_code' => 'litigation-dispute-resolution', 'name' => 'Paralegal', 'code' => 'paralegal-litigation', 'description' => 'Provides case, filing, and litigation support.'],
            ['department_code' => 'corporate-commercial', 'name' => 'Head of Corporate & Commercial', 'code' => 'head-of-corporate-commercial', 'description' => 'Leads corporate advisory and commercial transaction work.'],
            ['department_code' => 'corporate-commercial', 'name' => 'Senior Associate', 'code' => 'senior-associate-corporate', 'description' => 'Leads complex corporate and commercial mandates.'],
            ['department_code' => 'corporate-commercial', 'name' => 'Associate', 'code' => 'associate-corporate', 'description' => 'Supports advisory, transactional, and compliance work.'],
            ['department_code' => 'conveyancing-property', 'name' => 'Head of Conveyancing', 'code' => 'head-of-conveyancing', 'description' => 'Leads property and conveyancing practice work.'],
            ['department_code' => 'conveyancing-property', 'name' => 'Conveyancing Associate', 'code' => 'conveyancing-associate', 'description' => 'Manages conveyancing and property transaction matters.'],
            ['department_code' => 'conveyancing-property', 'name' => 'Conveyancing Clerk', 'code' => 'conveyancing-clerk', 'description' => 'Supports registrations, searches, and property-file administration.'],
            ['department_code' => 'finance-administration', 'name' => 'Finance & Administration Manager', 'code' => 'finance-administration-manager', 'description' => 'Leads finance controls, administration, and office support.'],
            ['department_code' => 'finance-administration', 'name' => 'Accountant', 'code' => 'accountant', 'description' => 'Manages accounting records, reconciliations, and financial reporting.'],
            ['department_code' => 'finance-administration', 'name' => 'Administrative Officer', 'code' => 'administrative-officer', 'description' => 'Provides office administration and operational support.'],
            ['department_code' => 'client-services-business-development', 'name' => 'Client Relations Officer', 'code' => 'client-relations-officer', 'description' => 'Coordinates client service, communications, and intake support.'],
            ['department_code' => 'client-services-business-development', 'name' => 'Business Development Officer', 'code' => 'business-development-officer', 'description' => 'Supports business development, proposals, and relationship growth.'],
        ];

        foreach ($positions as $position) {
            if (DB::table('positions')->where('code', $position['code'])->exists()) {
                continue;
            }

            DB::table('positions')->insert([
                'department_id' => $departmentIds[$position['department_code']],
                'name' => $position['name'],
                'code' => $position['code'],
                'description' => $position['description'],
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
