<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('priority')->default('normal')->index();
            $table->date('due_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['created_by_user_id', 'status']);
        });

        Schema::create('internal_task_user', function (Blueprint $table): void {
            $table->foreignId('internal_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at')->useCurrent();

            $table->unique(['internal_task_id', 'user_id']);
            $table->index(['user_id', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_task_user');
        Schema::dropIfExists('internal_tasks');
    }
};
