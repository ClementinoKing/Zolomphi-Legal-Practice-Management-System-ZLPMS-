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
            $role->assignedPermissions()->syncWithoutDetaching(
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
