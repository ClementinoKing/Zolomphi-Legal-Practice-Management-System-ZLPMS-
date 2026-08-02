<?php

namespace App\Policies;

use App\Models\Matter;
use App\Models\User;

class MatterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'matters.view.all',
            'matters.view.department',
            'matters.view.assigned',
        ]);
    }

    public function view(User $user, Matter $matter): bool
    {
        return $this->viewAny($user)
            && Matter::query()->whereKey($matter)->visibleTo($user)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('matters.create');
    }

    public function update(User $user, Matter $matter): bool
    {
        return $user->hasPermission('matters.update') && $this->view($user, $matter);
    }

    public function close(User $user, Matter $matter): bool
    {
        return $user->hasPermission('matters.close') && $this->view($user, $matter);
    }

    public function participantsManage(User $user, Matter $matter): bool
    {
        return $user->hasPermission('matters.participants.manage') && $this->view($user, $matter);
    }

    public function configure(User $user): bool
    {
        return $user->hasPermission('matters.configure');
    }
}
