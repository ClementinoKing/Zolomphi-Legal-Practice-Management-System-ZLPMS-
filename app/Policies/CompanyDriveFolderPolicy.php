<?php

namespace App\Policies;

use App\Models\CompanyDriveFolder;
use App\Models\User;

class CompanyDriveFolderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('company-files.view');
    }

    public function view(User $user, CompanyDriveFolder $folder): bool
    {
        return $this->viewAny($user)
            && ($user->hasPermission('matters.view.all')
                || ($folder->client !== null && $folder->client->matters()->visibleTo($user)->exists()));
    }

    public function upload(User $user, CompanyDriveFolder $folder): bool
    {
        return $user->hasPermission('company-files.upload') && $this->view($user, $folder);
    }

    public function manage(User $user, CompanyDriveFolder $folder): bool
    {
        return $user->hasPermission('company-files.manage') && $this->view($user, $folder);
    }
}
