<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();
        $folders = DB::table('company_drive_folders');
        $roots = [
            'clients' => 'Client files',
            'organizations' => 'Organization files',
        ];
        $rootIds = [];

        foreach ($roots as $library => $name) {
            $rootIds[$library] = $folders
                ->where('library', $library)
                ->where('is_system', true)
                ->whereNull('parent_id')
                ->value('id');

            if (! is_numeric($rootIds[$library])) {
                $rootIds[$library] = $folders->insertGetId([
                    'name' => $name,
                    'library' => $library,
                    'is_system' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $rootIds[$library] = (int) $rootIds[$library];
        }

        DB::table('clients')
            ->select(['id', 'type', 'full_name', 'organization_name', 'client_number'])
            ->orderBy('id')
            ->each(function (object $client) use ($folders, $rootIds, $now): void {
                $library = $client->type === 'organization' ? 'organizations' : 'clients';
                $folderId = $folders->where('client_id', $client->id)->value('id');

                if (! is_numeric($folderId)) {
                    $folderId = $folders->insertGetId([
                        'parent_id' => $rootIds[$library],
                        'client_id' => $client->id,
                        'name' => $client->organization_name ?? $client->full_name ?? $client->client_number,
                        'library' => $library,
                        'is_system' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $folderId = (int) $folderId;

                DB::table('documents')
                    ->where('client_id', $client->id)
                    ->whereNull('company_drive_folder_id')
                    ->update([
                        'company_drive_folder_id' => $folderId,
                        'updated_at' => $now,
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Folder assignments are intentionally preserved; removing them would lose user organization.
    }
};
