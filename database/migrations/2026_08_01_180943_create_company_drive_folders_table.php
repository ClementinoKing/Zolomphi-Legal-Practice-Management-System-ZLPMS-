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
        Schema::create('company_drive_folders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')
                ->nullable()
                ->index()
                ->constrained('company_drive_folders')
                ->restrictOnDelete();
            $table->foreignId('client_id')
                ->nullable()
                ->unique()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->index()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('name');
            $table->string('library')->default('general')->index();
            $table->boolean('is_system')->default(false)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'name']);
            $table->index(['library', 'parent_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_drive_folders');
    }
};
