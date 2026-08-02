<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\PracticeAccessCatalog;
use Illuminate\Database\Seeder;

class AdministrationSeeder extends Seeder
{
    /**
     * Seed the minimum access-control configuration needed to administer the firm.
     */
    public function run(): void
    {
        $this->replaceLegacyAdminRole();

        $permissions = collect(PracticeAccessCatalog::permissions())
            ->map(fn (array $attributes, string $key): Permission => Permission::query()->updateOrCreate(
                ['key' => $key],
                $attributes,
            ));

        foreach (PracticeAccessCatalog::roles() as $roleName => $definition) {
            $permissionKeys = $definition['permissions'] === '*'
                ? array_keys(PracticeAccessCatalog::permissions())
                : $definition['permissions'];
            $role = Role::query()->updateOrCreate(
                ['name' => $roleName],
                ['description' => $definition['description']],
            );
            $role->assignedPermissions()->sync(
                $permissions->only($permissionKeys)->pluck('id')->all(),
            );
            $role->update([
                'permissions' => $role->assignedPermissions()
                    ->pluck('permissions.key')
                    ->sort()
                    ->values()
                    ->all(),
            ]);
        }

        $initialUser = User::query()->where('email', 'paliponsemw@gmail.com')->first();

        if ($initialUser !== null) {
            $superAdmin = Role::query()->where('name', 'Super Admin')->firstOrFail();
            $initialUser->roles()->syncWithoutDetaching([$superAdmin->id]);
        }

        $this->assignDefaultRolesToUnassignedUsers();
    }

    /**
     * Initialize least-privilege roles for seeded or imported users without
     * replacing roles that an administrator has explicitly assigned.
     */
    private function assignDefaultRolesToUnassignedUsers(): void
    {
        $roles = Role::query()
            ->whereIn('name', [
                'Firm Administrator',
                'Managing Partner',
                'Department Head',
                'Attorney',
                'Paralegal & Clerk',
                'Finance Manager',
                'Finance Officer',
                'Client Services',
                'ICT Support',
                'Security Auditor',
            ])
            ->get()
            ->keyBy('name');

        User::query()
            ->whereDoesntHave('roles')
            ->with([
                'assignedDepartment:id,code',
                'assignedPosition:id,code,is_attorney',
            ])
            ->get()
            ->each(function (User $user) use ($roles): void {
                $roleName = $this->defaultRoleNameFor($user);

                if ($roleName === null || ! $roles->has($roleName)) {
                    return;
                }

                $role = $roles->get($roleName);

                if ($role === null) {
                    return;
                }

                $user->roles()->syncWithoutDetaching([$role->id]);
            });
    }

    /**
     * Resolve the catalog role that best matches the user's normalized staff assignment.
     */
    private function defaultRoleNameFor(User $user): ?string
    {
        $position = $user->assignedPosition;
        $department = $user->assignedDepartment;

        if ($position === null) {
            return null;
        }

        $positionCode = $position->code;
        $departmentCode = $department === null ? '' : $department->code;

        return match (true) {
            in_array($positionCode, ['managing-partner', 'equity-partner'], true) => 'Managing Partner',
            $positionCode === 'practice-manager' => 'Firm Administrator',
            str_starts_with($positionCode, 'head-') && $position->is_attorney => 'Department Head',
            $position->is_attorney => 'Attorney',
            str_contains($positionCode, 'paralegal') || str_contains($positionCode, 'clerk') => 'Paralegal & Clerk',
            $departmentCode === 'finance-administration' && $positionCode === 'finance-administration-manager' => 'Finance Manager',
            $departmentCode === 'finance-administration' && in_array($positionCode, ['accountant', 'administrative-officer'], true) => 'Finance Officer',
            $departmentCode === 'client-services-business-development' => 'Client Services',
            in_array($positionCode, ['ict-manager', 'ict-support-officer', 'systems-administrator', 'software-developer'], true) => 'ICT Support',
            $positionCode === 'information-security-officer' => 'Security Auditor',
            default => null,
        };
    }

    /**
     * Replace the original generic administrator role with the clearer firm-administration template.
     */
    private function replaceLegacyAdminRole(): void
    {
        $legacyAdmin = Role::query()->where('name', 'Admin')->first();

        if ($legacyAdmin === null) {
            return;
        }

        $firmAdministrator = Role::query()->firstOrCreate(
            ['name' => 'Firm Administrator'],
            ['description' => PracticeAccessCatalog::roles()['Firm Administrator']['description']],
        );

        $firmAdministrator->users()->syncWithoutDetaching(
            $legacyAdmin->users()->pluck('users.id')->all(),
        );
        $firmAdministrator->assignedPermissions()->syncWithoutDetaching(
            $legacyAdmin->assignedPermissions()->pluck('permissions.id')->all(),
        );
        $legacyAdmin->delete();
    }
}
