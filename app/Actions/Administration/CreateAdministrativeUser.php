<?php

namespace App\Actions\Administration;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateAdministrativeUser
{
    /**
     * @param  array{name: string, email: string, phone?: string|null, department_id?: int|null, position_id?: int|null, status: string, role_ids: array<int, int>}  $attributes
     */
    public function handle(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            $user = User::create([
                ...Arr::only($attributes, ['name', 'email', 'phone', 'department_id', 'position_id', 'status']),
                'password' => Str::password(32),
            ]);

            $user->roles()->sync($attributes['role_ids']);

            return $user;
        });
    }
}
