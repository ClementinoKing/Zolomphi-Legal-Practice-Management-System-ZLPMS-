<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        $staff = [
            ['name' => 'Mphatso Banda', 'email' => 'mphatso.banda@zolomphi.co.mw', 'phone' => '+265991100001', 'department_code' => 'management-partnerships', 'position_code' => 'managing-partner'],
            ['name' => 'Thandiwe Moyo', 'email' => 'thandiwe.moyo@zolomphi.co.mw', 'phone' => '+265991100002', 'department_code' => 'management-partnerships', 'position_code' => 'equity-partner'],
            ['name' => 'Chikondi Phiri', 'email' => 'chikondi.phiri@zolomphi.co.mw', 'phone' => '+265991100003', 'department_code' => 'management-partnerships', 'position_code' => 'practice-manager'],
            ['name' => 'Kondwani Chirwa', 'email' => 'kondwani.chirwa@zolomphi.co.mw', 'phone' => '+265991100004', 'department_code' => 'litigation-dispute-resolution', 'position_code' => 'head-of-litigation'],
            ['name' => 'Ruth Nkhoma', 'email' => 'ruth.nkhoma@zolomphi.co.mw', 'phone' => '+265991100005', 'department_code' => 'litigation-dispute-resolution', 'position_code' => 'senior-associate-litigation'],
            ['name' => 'Tionge Msowoya', 'email' => 'tionge.msowoya@zolomphi.co.mw', 'phone' => '+265991100006', 'department_code' => 'litigation-dispute-resolution', 'position_code' => 'associate-litigation'],
            ['name' => 'Gift Kachere', 'email' => 'gift.kachere@zolomphi.co.mw', 'phone' => '+265991100007', 'department_code' => 'litigation-dispute-resolution', 'position_code' => 'associate-litigation'],
            ['name' => 'Yamikani Ngoma', 'email' => 'yamikani.ngoma@zolomphi.co.mw', 'phone' => '+265991100008', 'department_code' => 'litigation-dispute-resolution', 'position_code' => 'paralegal-litigation'],
            ['name' => 'Tamara Chisale', 'email' => 'tamara.chisale@zolomphi.co.mw', 'phone' => '+265991100009', 'department_code' => 'litigation-dispute-resolution', 'position_code' => 'paralegal-litigation'],
            ['name' => 'Peter Kamwendo', 'email' => 'peter.kamwendo@zolomphi.co.mw', 'phone' => '+265991100010', 'department_code' => 'corporate-commercial', 'position_code' => 'head-of-corporate-commercial'],
            ['name' => 'Esnart Chilima', 'email' => 'esnart.chilima@zolomphi.co.mw', 'phone' => '+265991100011', 'department_code' => 'corporate-commercial', 'position_code' => 'senior-associate-corporate'],
            ['name' => 'Blessings Nthenda', 'email' => 'blessings.nthenda@zolomphi.co.mw', 'phone' => '+265991100012', 'department_code' => 'corporate-commercial', 'position_code' => 'associate-corporate'],
            ['name' => 'Beatrice Mbewe', 'email' => 'beatrice.mbewe@zolomphi.co.mw', 'phone' => '+265991100013', 'department_code' => 'corporate-commercial', 'position_code' => 'associate-corporate'],
            ['name' => 'Lloyd Kalua', 'email' => 'lloyd.kalua@zolomphi.co.mw', 'phone' => '+265991100014', 'department_code' => 'corporate-commercial', 'position_code' => 'associate-corporate'],
            ['name' => 'Madalitso Mvula', 'email' => 'madalitso.mvula@zolomphi.co.mw', 'phone' => '+265991100015', 'department_code' => 'conveyancing-property', 'position_code' => 'head-of-conveyancing'],
            ['name' => 'Natasha Zulu', 'email' => 'natasha.zulu@zolomphi.co.mw', 'phone' => '+265991100016', 'department_code' => 'conveyancing-property', 'position_code' => 'conveyancing-associate'],
            ['name' => 'Allan Mhone', 'email' => 'allan.mhone@zolomphi.co.mw', 'phone' => '+265991100017', 'department_code' => 'conveyancing-property', 'position_code' => 'conveyancing-clerk'],
            ['name' => 'Mary Nkhata', 'email' => 'mary.nkhata@zolomphi.co.mw', 'phone' => '+265991100018', 'department_code' => 'conveyancing-property', 'position_code' => 'conveyancing-clerk'],
            ['name' => 'Linda Kaponda', 'email' => 'linda.kaponda@zolomphi.co.mw', 'phone' => '+265991100019', 'department_code' => 'finance-administration', 'position_code' => 'finance-administration-manager'],
            ['name' => 'Owen Nyirenda', 'email' => 'owen.nyirenda@zolomphi.co.mw', 'phone' => '+265991100020', 'department_code' => 'finance-administration', 'position_code' => 'accountant'],
            ['name' => 'Gertrude Manda', 'email' => 'gertrude.manda@zolomphi.co.mw', 'phone' => '+265991100021', 'department_code' => 'finance-administration', 'position_code' => 'accountant'],
            ['name' => 'Kelvin Soko', 'email' => 'kelvin.soko@zolomphi.co.mw', 'phone' => '+265991100022', 'department_code' => 'finance-administration', 'position_code' => 'administrative-officer'],
            ['name' => 'Brenda Kumbukani', 'email' => 'brenda.kumbukani@zolomphi.co.mw', 'phone' => '+265991100023', 'department_code' => 'client-services-business-development', 'position_code' => 'client-relations-officer'],
            ['name' => 'Isaac Mkandawire', 'email' => 'isaac.mkandawire@zolomphi.co.mw', 'phone' => '+265991100024', 'department_code' => 'client-services-business-development', 'position_code' => 'business-development-officer'],
            ['name' => 'Patricia Gondwe', 'email' => 'patricia.gondwe@zolomphi.co.mw', 'phone' => '+265991100025', 'department_code' => 'client-services-business-development', 'position_code' => 'client-relations-officer'],
        ];

        $timestamp = now();
        $password = Hash::make('12345678');
        $departmentCodes = array_unique(array_column($staff, 'department_code'));
        $positionCodes = array_unique(array_column($staff, 'position_code'));
        $departments = DB::table('departments')
            ->whereIn('code', $departmentCodes)
            ->get(['id', 'name', 'code'])
            ->keyBy('code');
        $positions = DB::table('positions')
            ->whereIn('code', $positionCodes)
            ->get(['id', 'department_id', 'code'])
            ->keyBy('code');

        foreach ($staff as $staffMember) {
            $department = $departments->get($staffMember['department_code']);
            $position = $positions->get($staffMember['position_code']);

            if ($department === null || $position === null || $position->department_id !== $department->id) {
                throw new RuntimeException('The law-firm staff migration requires the configured non-ICT departments and positions.');
            }

            DB::table('users')->updateOrInsert(
                ['email' => $staffMember['email']],
                [
                    'name' => $staffMember['name'],
                    'password' => $password,
                    'phone' => $staffMember['phone'],
                    'department' => $department->name,
                    'department_id' => $department->id,
                    'position_id' => $position->id,
                    'status' => 'active',
                    'email_verified_at' => $timestamp,
                    'password_changed_at' => $timestamp,
                    'updated_at' => $timestamp,
                    'created_at' => $timestamp,
                ],
            );
        }
    }

    public function down(): void {}
};
