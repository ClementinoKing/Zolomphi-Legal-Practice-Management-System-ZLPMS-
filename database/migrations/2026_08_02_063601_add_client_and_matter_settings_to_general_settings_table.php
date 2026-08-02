<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('general_settings', function (Blueprint $table): void {
            $table->json('client_settings')->nullable()->after('invoice_settings');
            $table->json('matter_settings')->nullable()->after('client_settings');
        });
    }

    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table): void {
            $table->dropColumn(['client_settings', 'matter_settings']);
        });
    }
};
