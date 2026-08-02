<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_trackers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('matter_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('running')->index();
            $table->timestamp('started_at');
            $table->timestamp('last_resumed_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->unsignedBigInteger('accumulated_seconds')->default(0);
            $table->timestamps();

            $table->index(['matter_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_trackers');
    }
};
