<?php

namespace App\Actions\CompanyDrive;

use App\Models\Client;
use App\Models\CompanyDriveFolder;
use App\Models\User;

class EnsureClientDriveFolder
{
    public function handle(
        Client $client,
        ?User $createdBy = null,
    ): CompanyDriveFolder {
        $library = $client->type === 'organization'
            ? CompanyDriveFolder::LibraryOrganizations
            : CompanyDriveFolder::LibraryClients;
        $root = CompanyDriveFolder::query()->firstOrCreate(
            [
                'parent_id' => null,
                'client_id' => null,
                'library' => $library,
                'is_system' => true,
            ],
            [
                'name' => $library === CompanyDriveFolder::LibraryOrganizations
                    ? 'Organization files'
                    : 'Client files',
            ],
        );
        $folder = CompanyDriveFolder::query()->firstOrNew([
            'client_id' => $client->id,
        ]);

        $folder->fill([
            'parent_id' => $root->id,
            'created_by_user_id' => $folder->exists
                ? $folder->created_by_user_id
                : $createdBy?->id,
            'name' => $client->organization_name ?? $client->full_name ?? $client->client_number,
            'library' => $library,
            'is_system' => true,
        ]);
        $folder->save();

        return $folder;
    }
}
