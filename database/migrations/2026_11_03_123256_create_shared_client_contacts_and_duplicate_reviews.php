<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_contact_points', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 16);
            $table->string('value', 255);
            $table->string('normalized_value', 255);
            $table->timestamps();
            $table->unique(['type', 'normalized_value']);
        });

        Schema::table('clients', function (Blueprint $table): void {
            $table->string('normalized_name')->nullable()->index();
            $table->foreignId('email_contact_id')->nullable()->constrained('client_contact_points')->nullOnDelete();
            $table->foreignId('phone_contact_id')->nullable()->constrained('client_contact_points')->nullOnDelete();
            $table->foreignId('alternate_phone_contact_id')->nullable()->constrained('client_contact_points')->nullOnDelete();
        });

        Schema::create('client_duplicate_name_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('normalized_name')->unique();
            $table->timestamps();
        });

        DB::table('clients')->orderBy('id')->chunkById(200, function ($clients): void {
            foreach ($clients as $client) {
                $name = $client->full_name ?: $client->organization_name;
                $normalizedName = $name === null ? null : $this->normalizeName($name);
                $updates = [
                    'normalized_name' => $normalizedName,
                ];

                if ($normalizedName !== null && $normalizedName !== '') {
                    DB::table('client_duplicate_name_keys')->insertOrIgnore([
                        'normalized_name' => $normalizedName,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                foreach ([
                    'email' => 'email_contact_id',
                    'phone' => 'phone_contact_id',
                    'alternate_phone' => 'alternate_phone_contact_id',
                ] as $source => $target) {
                    $value = trim((string) ($client->{$source} ?? ''));

                    if ($value === '') {
                        continue;
                    }

                    $type = $source === 'email' ? 'email' : 'phone';
                    $normalizedValue = $this->normalizeContact($type, $value);
                    $contactPointId = DB::table('client_contact_points')
                        ->where('type', $type)
                        ->where('normalized_value', $normalizedValue)
                        ->value('id');

                    if ($contactPointId === null) {
                        $now = now();
                        $contactPointId = DB::table('client_contact_points')->insertGetId([
                            'type' => $type,
                            'value' => $value,
                            'normalized_value' => $normalizedValue,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    $updates[$target] = $contactPointId;
                }

                DB::table('clients')->where('id', $client->id)->update($updates);
            }
        });

        Schema::create('client_duplicate_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('decision', 20);
            $table->json('matched_fields');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at');
            $table->timestamps();
            $table->unique(['client_id', 'candidate_client_id']);
            $table->index(['decision', 'reviewed_at']);
        });

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropIndex(['email']);
            $table->dropIndex(['phone']);
            $table->dropColumn(['email', 'phone', 'alternate_phone']);
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable()->index();
            $table->string('alternate_phone')->nullable();
        });

        DB::table('clients')->orderBy('id')->chunkById(200, function ($clients): void {
            foreach ($clients as $client) {
                DB::table('clients')->where('id', $client->id)->update([
                    'email' => $this->contactValue($client->email_contact_id),
                    'phone' => $this->contactValue($client->phone_contact_id),
                    'alternate_phone' => $this->contactValue($client->alternate_phone_contact_id),
                ]);
            }
        });

        Schema::dropIfExists('client_duplicate_reviews');
        Schema::dropIfExists('client_duplicate_name_keys');

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropForeign(['email_contact_id']);
            $table->dropForeign(['phone_contact_id']);
            $table->dropForeign(['alternate_phone_contact_id']);
            $table->dropIndex(['normalized_name']);
            $table->dropColumn([
                'email_contact_id',
                'phone_contact_id',
                'alternate_phone_contact_id',
                'normalized_name',
            ]);
        });

        Schema::dropIfExists('client_contact_points');
    }

    private function normalizeName(string $value): string
    {
        return preg_replace('/[^\pL\pN]+/u', '', mb_strtolower(trim($value))) ?? '';
    }

    private function normalizeContact(string $type, string $value): string
    {
        if ($type === 'email') {
            return mb_strtolower(trim($value));
        }

        return preg_replace('/[\s().-]+/u', '', trim($value)) ?? '';
    }

    private function contactValue(?int $contactPointId): ?string
    {
        if ($contactPointId === null) {
            return null;
        }

        $value = DB::table('client_contact_points')->where('id', $contactPointId)->value('value');

        return is_string($value) ? $value : null;
    }
};
