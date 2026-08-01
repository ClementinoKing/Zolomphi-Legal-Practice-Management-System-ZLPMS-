<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdministrationSeeder extends Seeder
{
    /**
     * Seed the minimum access-control configuration needed to administer the firm.
     */
    public function run(): void
    {
        $permissions = collect([
            ['key' => 'administration.access', 'name' => 'Access administration', 'group' => 'Administration', 'description' => 'Open the administration workspace.'],
            ['key' => 'firm-settings.manage', 'name' => 'Manage firm settings', 'group' => 'Firm', 'description' => 'Maintain firm identity and contact settings.'],
            ['key' => 'users.manage', 'name' => 'Manage users', 'group' => 'Access', 'description' => 'Create and update user access.'],
            ['key' => 'departments.manage', 'name' => 'Manage departments', 'group' => 'Firm', 'description' => 'Maintain the law firm department directory.'],
            ['key' => 'roles.manage', 'name' => 'Manage roles', 'group' => 'Access', 'description' => 'Create and update roles.'],
            ['key' => 'permissions.manage', 'name' => 'Manage permissions', 'group' => 'Access', 'description' => 'Create and update permissions.'],
            ['key' => 'matter-types.manage', 'name' => 'Manage matter types', 'group' => 'Practice', 'description' => 'Maintain matter-type configuration.'],
            ['key' => 'billing-items.manage', 'name' => 'Manage billing items', 'group' => 'Billing', 'description' => 'Maintain invoice item configuration.'],
            ['key' => 'expense-categories.manage', 'name' => 'Manage expense categories', 'group' => 'Billing', 'description' => 'Maintain expense category configuration.'],
            ['key' => 'payment-methods.manage', 'name' => 'Manage payment methods', 'group' => 'Billing', 'description' => 'Maintain accepted payment methods.'],
        ])->map(fn (array $attributes): Permission => Permission::query()->updateOrCreate(
            ['key' => $attributes['key']],
            $attributes,
        ));

        $permissionIds = $permissions->pluck('id')->all();
        $permissionKeys = $permissions->pluck('key')->all();

        foreach (['Super Admin', 'Admin'] as $roleName) {
            $role = Role::query()->updateOrCreate(
                ['name' => $roleName],
                [
                    'description' => $roleName === 'Super Admin'
                        ? 'Full access to all firm administration controls.'
                        : 'Administrative access to firm configuration and user management.',
                    'permissions' => $permissionKeys,
                ],
            );

            $role->assignedPermissions()->sync($permissionIds);
        }

        $initialUser = User::query()->where('email', 'paliponsemw@gmail.com')->first();

        if ($initialUser !== null) {
            $superAdmin = Role::query()->where('name', 'Super Admin')->firstOrFail();
            $initialUser->roles()->syncWithoutDetaching([$superAdmin->id]);
        }
    }
}
