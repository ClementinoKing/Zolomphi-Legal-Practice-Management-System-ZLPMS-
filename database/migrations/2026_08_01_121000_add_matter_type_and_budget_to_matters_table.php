<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matters', function (Blueprint $table): void {
            $table->foreignId('matter_type_id')
                ->nullable()
                ->after('client_id')
                ->constrained()
                ->restrictOnDelete();
            $table->decimal('budget_amount', 15, 2)
                ->nullable()
                ->after('default_hourly_rate');
        });
    }

    public function down(): void
    {
        Schema::table('matters', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('matter_type_id');
            $table->dropColumn('budget_amount');
        });
    }
};
