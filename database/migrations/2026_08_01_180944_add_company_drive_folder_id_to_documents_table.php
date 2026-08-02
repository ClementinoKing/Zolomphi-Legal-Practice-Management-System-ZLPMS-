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
        Schema::table('documents', function (Blueprint $table): void {
            $table->foreignId('company_drive_folder_id')
                ->nullable()
                ->index()
                ->constrained('company_drive_folders')
                ->nullOnDelete();

            $table->index(['company_drive_folder_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropIndex(['company_drive_folder_id', 'created_at']);
            $table->dropConstrainedForeignId('company_drive_folder_id');
        });
    }
};
