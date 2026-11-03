<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientContactPoint;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ClientContactManager
{
    public function normalizeName(string $value): string
    {
        return preg_replace('/[^\pL\pN]+/u', '', mb_strtolower(trim($value))) ?? '';
    }

    public function normalizeEmail(?string $value): ?string
    {
        $normalizedValue = mb_strtolower(trim((string) $value));

        return $normalizedValue === '' ? null : $normalizedValue;
    }

    public function normalizePhone(?string $value): ?string
    {
        $trimmedValue = trim((string) $value);

        if ($trimmedValue === '') {
            return null;
        }

        return preg_replace('/[\s().-]+/u', '', $trimmedValue) ?? '';
    }

    public function resolveId(string $type, ?string $value): ?int
    {
        $normalizedValue = match ($type) {
            'email' => $this->normalizeEmail($value),
            'phone' => $this->normalizePhone($value),
            default => throw new \InvalidArgumentException('Unsupported client contact type.'),
        };

        if ($normalizedValue === null) {
            return null;
        }

        $contactPoint = ClientContactPoint::query()->firstOrCreate(
            ['type' => $type, 'normalized_value' => $normalizedValue],
            ['value' => trim((string) $value)],
        );

        return $contactPoint->id;
    }

    public function lockName(string $value): void
    {
        $normalizedName = $this->normalizeName($value);

        if ($normalizedName === '') {
            return;
        }

        $now = now();
        DB::table('client_duplicate_name_keys')->insertOrIgnore([
            'normalized_name' => $normalizedName,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('client_duplicate_name_keys')
            ->where('normalized_name', $normalizedName)
            ->lockForUpdate()
            ->first();
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, int|null>
     */
    public function resolveAttributes(array $attributes): array
    {
        return [
            'email_contact_id' => $this->resolveId('email', $this->stringValue($attributes, 'email')),
            'phone_contact_id' => $this->resolveId('phone', $this->stringValue($attributes, 'phone')),
            'alternate_phone_contact_id' => $this->resolveId('phone', $this->stringValue($attributes, 'alternate_phone')),
        ];
    }

    /** @param list<int|null> $contactPointIds */
    public function deleteUnused(array $contactPointIds): void
    {
        foreach (array_unique(array_filter($contactPointIds)) as $contactPointId) {
            $isAssigned = Client::withTrashed()
                ->where('email_contact_id', $contactPointId)
                ->orWhere('phone_contact_id', $contactPointId)
                ->orWhere('alternate_phone_contact_id', $contactPointId)
                ->exists();

            if (! $isAssigned) {
                ClientContactPoint::query()->whereKey($contactPointId)->delete();
            }
        }
    }

    /**
     * @param  list<array{type: string, normalized_value: string}>  $contacts
     * @return Collection<int, ClientContactPoint>
     */
    public function findExisting(array $contacts): Collection
    {
        $lookup = collect($contacts)
            ->filter(fn (array $contact): bool => is_string($contact['normalized_value'] ?? null)
                && $contact['normalized_value'] !== '')
            ->unique(fn (array $contact): string => $contact['type'].':'.$contact['normalized_value']);

        if ($lookup->isEmpty()) {
            return new Collection;
        }

        return ClientContactPoint::query()
            ->where(function ($query) use ($lookup): void {
                foreach ($lookup as $contact) {
                    $query->orWhere(fn ($contactQuery) => $contactQuery
                        ->where('type', $contact['type'])
                        ->where('normalized_value', $contact['normalized_value']));
                }
            })
            ->get(['id', 'type', 'normalized_value']);
    }

    /** @param array<string, mixed> $attributes */
    private function stringValue(array $attributes, string $key): ?string
    {
        $value = $attributes[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
