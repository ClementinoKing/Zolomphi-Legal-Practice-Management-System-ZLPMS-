<?php

namespace App\Services;

use App\Models\Client;

class ClientDuplicateMatcher
{
    public function __construct(private ClientContactManager $contacts) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<array{id: int, client_number: string, type: string, name: string, matched_fields: list<string>}>
     */
    public function findCandidates(array $attributes): array
    {
        $name = $this->contacts->normalizeName($this->stringValue($attributes, 'name') ?? '');
        $email = $this->contacts->normalizeEmail($this->stringValue($attributes, 'email'));
        $phones = array_values(array_filter([
            $this->contacts->normalizePhone($this->stringValue($attributes, 'phone')),
            $this->contacts->normalizePhone($this->stringValue($attributes, 'alternate_phone')),
        ]));

        $contactPoints = $this->contacts->findExisting([
            ...($email === null ? [] : [['type' => 'email', 'normalized_value' => $email]]),
            ...array_map(fn (string $phone): array => ['type' => 'phone', 'normalized_value' => $phone], $phones),
        ]);

        $emailContactIds = $contactPoints->where('type', 'email')->modelKeys();
        $phoneContactIds = $contactPoints->where('type', 'phone')->modelKeys();

        if ($name === '' && $emailContactIds === [] && $phoneContactIds === []) {
            return [];
        }

        $candidates = Client::query()
            ->where(function ($query) use ($name, $emailContactIds, $phoneContactIds): void {
                if ($name !== '') {
                    $query->where('normalized_name', $name);
                }

                if ($emailContactIds !== []) {
                    $query->orWhereIn('email_contact_id', $emailContactIds);
                }

                if ($phoneContactIds !== []) {
                    $query->orWhereIn('phone_contact_id', $phoneContactIds)
                        ->orWhereIn('alternate_phone_contact_id', $phoneContactIds);
                }
            })
            ->get([
                'id',
                'client_number',
                'type',
                'full_name',
                'organization_name',
                'normalized_name',
                'email_contact_id',
                'phone_contact_id',
                'alternate_phone_contact_id',
            ]);

        return $candidates
            ->map(function (Client $client) use ($name, $emailContactIds, $phoneContactIds): array {
                $matchedFields = [];

                if ($name !== '' && $client->normalized_name === $name) {
                    $matchedFields[] = 'name';
                }

                if ($client->email_contact_id !== null && in_array((int) $client->email_contact_id, $emailContactIds, true)) {
                    $matchedFields[] = 'email';
                }

                if (in_array((int) $client->phone_contact_id, $phoneContactIds, true)
                    || in_array((int) $client->alternate_phone_contact_id, $phoneContactIds, true)) {
                    $matchedFields[] = 'phone';
                }

                return [
                    'id' => $client->id,
                    'client_number' => $client->client_number,
                    'type' => $client->type,
                    'name' => $client->full_name ?? $client->organization_name ?? 'Unnamed client',
                    'matched_fields' => $matchedFields,
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $attributes */
    private function stringValue(array $attributes, string $key): ?string
    {
        $value = $attributes[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
