<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table): void {
            $table->boolean('is_attorney')->default(false)->index();
        });

        Schema::table('matters', function (Blueprint $table): void {
            $table->date('deadline_at')->nullable()->index();
        });

        Schema::create('matter_attorney', function (Blueprint $table): void {
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at')->useCurrent();

            $table->unique(['matter_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matter_attorney');

        Schema::table('matters', function (Blueprint $table): void {
            $table->dropIndex(['deadline_at']);
            $table->dropColumn('deadline_at');
        });

        Schema::table('positions', function (Blueprint $table): void {
            $table->dropIndex(['is_attorney']);
            $table->dropColumn('is_attorney');
        });
    }
};
