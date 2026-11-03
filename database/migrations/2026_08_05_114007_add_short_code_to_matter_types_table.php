<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('matter_types', function (Blueprint $table): void {
            $table->string('short_code', 10)->nullable()->after('code');
        });

        DB::table('matter_types')->orderBy('id')->eachById(function (object $matterType): void {
            $baseShortCode = collect(preg_split('/[^A-Za-z0-9]+/', $matterType->code) ?: [])
                ->filter()
                ->map(fn (string $segment): string => Str::upper(Str::substr($segment, 0, 1)))
                ->implode('');
            $baseShortCode = Str::substr($baseShortCode !== '' ? $baseShortCode : Str::upper($matterType->code), 0, 10);
            $shortCode = $baseShortCode;
            $suffix = 2;

            while (DB::table('matter_types')->where('short_code', $shortCode)->where('id', '!=', $matterType->id)->exists()) {
                $suffixValue = (string) $suffix;
                $shortCode = Str::substr($baseShortCode, 0, 10 - Str::length($suffixValue)).$suffixValue;
                $suffix++;
            }

            DB::table('matter_types')->where('id', $matterType->id)->update(['short_code' => $shortCode]);
        });

        Schema::table('matter_types', function (Blueprint $table): void {
            $table->unique('short_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matter_types', function (Blueprint $table): void {
            $table->dropUnique(['short_code']);
            $table->dropColumn('short_code');
        });
    }
};
