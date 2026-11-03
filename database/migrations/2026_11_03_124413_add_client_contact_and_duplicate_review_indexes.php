<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ([
            'clients' => ['email_contact_id', 'phone_contact_id', 'alternate_phone_contact_id'],
            'client_duplicate_reviews' => ['candidate_client_id'],
        ] as $tableName => $columns) {
            $indexes = Schema::getIndexes($tableName);

            foreach ($columns as $column) {
                $hasSingleColumnIndex = collect($indexes)->contains(
                    fn (array $index): bool => $index['columns'] === [$column],
                );

                if (! $hasSingleColumnIndex) {
                    Schema::table($tableName, function (Blueprint $table) use ($column): void {
                        $table->index($column);
                    });
                    $indexes = Schema::getIndexes($tableName);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ([
            'clients' => ['email_contact_id', 'phone_contact_id', 'alternate_phone_contact_id'],
            'client_duplicate_reviews' => ['candidate_client_id'],
        ] as $tableName => $columns) {
            $indexes = collect(Schema::getIndexes($tableName))->keyBy('name');

            foreach ($columns as $column) {
                $indexName = $tableName.'_'.$column.'_index';

                if ($indexes->has($indexName)) {
                    Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                        $table->dropIndex($indexName);
                    });
                }
            }
        }
    }
};
