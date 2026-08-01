<?php

namespace App\Actions\Administration;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateAdministrativeUser
{
    /**
     * @param  array{name: string, email: string, phone?: string|null, department_id?: int|null, position_id?: int|null, status: string, role_ids: array<int, int>}  $attributes
     */
    public function handle(User $user, array $attributes): User
    {
        return DB::transaction(function () use ($attributes, $user): User {
            $user->fill(Arr::only($attributes, ['name', 'email', 'phone', 'department_id', 'position_id', 'status']));

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $user->save();
            $user->roles()->sync($attributes['role_ids']);

            return $user;
        });
    }
}
