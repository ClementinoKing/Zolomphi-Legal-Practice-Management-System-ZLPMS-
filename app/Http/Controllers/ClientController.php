<?php

namespace App\Http\Controllers;

use App\Actions\Clients\GenerateClientNumber;
use App\Actions\CompanyDrive\EnsureClientDriveFolder;
use App\Http\Requests\ClientDuplicateCandidatesRequest;
use App\Http\Requests\ClientRequest;
use App\Models\Client;
use App\Models\ClientContactPoint;
use App\Models\ClientDuplicateReview;
use App\Models\GeneralSetting;
use App\Models\User;
use App\Services\ClientContactManager;
use App\Services\ClientDuplicateMatcher;
use App\Services\PracticeRecordSettings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(): Response
    {
        $settings = app(PracticeRecordSettings::class)->clientValues(GeneralSetting::query()->first());
        $clients = Client::query()
            ->with([
                'emailContact:id,value',
                'phoneContact:id,value',
                'alternatePhoneContact:id,value',
            ])
            ->orderBy('organization_name')
            ->orderBy('full_name')
            ->get([
                'id',
                'type',
                'full_name',
                'organization_name',
                'client_number',
                'contact_person',
                'email_contact_id',
                'phone_contact_id',
                'alternate_phone_contact_id',
                'tax_number',
                'registration_number',
                'address_line1',
                'address_line2',
                'city',
                'state',
                'postal_code',
                'country',
                'notes',
                'metadata',
                'status',
            ]);
        $linkedClients = $this->linkedClients($clients);

        return Inertia::render('clients/index', [
            'clients' => $clients
                ->map(fn (Client $client): array => [
                    'id' => $client->id,
                    'client_type' => $client->type,
                    'name' => $client->full_name ?? $client->organization_name ?? 'Unnamed client',
                    'code' => $client->client_number,
                    'contact_person' => $client->contact_person,
                    'email' => $client->email,
                    'phone' => $client->phone,
                    'alternate_phone' => $client->alternate_phone,
                    'tax_number' => $client->tax_number,
                    'registration_number' => $client->registration_number,
                    'address_line1' => $client->address_line1,
                    'address_line2' => $client->address_line2,
                    'city' => $client->city,
                    'state' => $client->state,
                    'postal_code' => $client->postal_code,
                    'country' => $client->country,
                    'notes' => $client->notes,
                    'custom_fields' => $client->metadata['custom_fields'] ?? [],
                    'is_active' => $client->status === 'active',
                    'linked_clients' => $linkedClients[$client->id] ?? [],
                ])
                ->all(),
            'customFields' => $settings['fields'],
        ]);
    }

    public function duplicateCandidates(
        ClientDuplicateCandidatesRequest $request,
        ClientDuplicateMatcher $duplicateMatcher,
    ): JsonResponse {
        return response()->json([
            'candidates' => $duplicateMatcher->findCandidates($request->validated()),
        ]);
    }

    public function store(
        ClientRequest $request,
        GenerateClientNumber $generateClientNumber,
        EnsureClientDriveFolder $ensureClientDriveFolder,
        ClientContactManager $contactManager,
        ClientDuplicateMatcher $duplicateMatcher,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $generateClientNumber, $ensureClientDriveFolder, $contactManager, $duplicateMatcher): void {
            $attributes = $this->clientAttributes($request);
            $contactAttributes = $contactManager->resolveAttributes($attributes);
            $contactManager->lockName($attributes['full_name'] ?? $attributes['organization_name'] ?? '');
            ClientContactPoint::query()->whereIn('id', array_values(array_filter($contactAttributes)))->lockForUpdate()->get();
            $candidates = $duplicateMatcher->findCandidates($attributes);
            $decisions = $this->duplicateDecisions($request);
            $this->ensureCandidateDecisions($candidates, $decisions);

            $attributes = [...Arr::except($attributes, ['email', 'phone', 'alternate_phone']), ...$contactAttributes];
            $attributes['client_number'] = $generateClientNumber->handle();
            $attributes['metadata'] = ['custom_fields' => $request->customFields()];
            $client = Client::create($attributes);
            $this->storeDuplicateReviews($client, $candidates, $decisions, $request->user());
            $ensureClientDriveFolder->handle($client, $request->user());
        });

        return $this->success('Client created.');
    }

    public function update(
        ClientRequest $request,
        Client $client,
        EnsureClientDriveFolder $ensureClientDriveFolder,
        ClientContactManager $contactManager,
    ): RedirectResponse {
        $attributes = $this->clientAttributes($request);
        $previousContactPointIds = [
            $client->email_contact_id,
            $client->phone_contact_id,
            $client->alternate_phone_contact_id,
        ];
        $attributes['metadata'] = [
            ...($client->metadata ?? []),
            'custom_fields' => [
                ...($client->metadata['custom_fields'] ?? []),
                ...$request->customFields(),
            ],
        ];
        DB::transaction(function () use ($client, $attributes, $ensureClientDriveFolder, $request, $contactManager, $previousContactPointIds): void {
            $contactAttributes = $contactManager->resolveAttributes($attributes);
            ClientContactPoint::query()->whereIn('id', array_values(array_filter($contactAttributes)))->lockForUpdate()->get();
            $attributes = [...Arr::except($attributes, ['email', 'phone', 'alternate_phone']), ...$contactAttributes];
            $client->update($attributes);
            $ensureClientDriveFolder->handle($client, $request->user());
            $contactManager->deleteUnused($previousContactPointIds);
        });

        return $this->success('Client updated.');
    }

    private function success(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('clients.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function clientAttributes(ClientRequest $request): array
    {
        return Arr::only($request->validated(), [
            'type',
            'full_name',
            'organization_name',
            'contact_person',
            'email',
            'phone',
            'alternate_phone',
            'tax_number',
            'registration_number',
            'address_line1',
            'address_line2',
            'city',
            'state',
            'postal_code',
            'country',
            'notes',
            'status',
        ]);
    }

    /**
     * @param  list<array{id: int, client_number: string, type: string, name: string, matched_fields: list<string>}>  $candidates
     * @param  array<int, string>  $decisions
     */
    private function ensureCandidateDecisions(array $candidates, array $decisions): void
    {
        $candidateIds = collect($candidates)->pluck('id')->map(fn (int $id): int => $id)->sort()->values()->all();
        $decisionIds = array_keys($decisions);
        sort($decisionIds);

        if ($candidateIds !== $decisionIds) {
            throw ValidationException::withMessages([
                'duplicate_decisions' => 'A possible matching client was added or changed. Check the matches again before saving.',
            ]);
        }
    }

    /** @return array<int, string> */
    private function duplicateDecisions(ClientRequest $request): array
    {
        $decisions = [];

        foreach ($request->validated('duplicate_decisions', []) as $decision) {
            $decisions[(int) $decision['candidate_id']] = $decision['decision'];
        }

        return $decisions;
    }

    /**
     * @param  list<array{id: int, client_number: string, type: string, name: string, matched_fields: list<string>}>  $candidates
     * @param  array<int, string>  $decisions
     */
    private function storeDuplicateReviews(Client $client, array $candidates, array $decisions, ?User $user): void
    {
        foreach ($candidates as $candidate) {
            $clientId = min($client->id, $candidate['id']);
            $candidateClientId = max($client->id, $candidate['id']);

            ClientDuplicateReview::query()->updateOrCreate(
                ['client_id' => $clientId, 'candidate_client_id' => $candidateClientId],
                [
                    'decision' => $decisions[$candidate['id']],
                    'matched_fields' => $candidate['matched_fields'],
                    'reviewed_by_user_id' => $user?->id,
                    'reviewed_at' => now(),
                ],
            );
        }
    }

    /**
     * @param  Collection<int, Client>  $clients
     * @return array<int, list<array{id: int, name: string, code: string, type: string, decision: string}>>
     */
    private function linkedClients(Collection $clients): array
    {
        $clientIds = $clients->modelKeys();
        $contactPointIds = $clients
            ->flatMap(fn (Client $client): array => array_filter([
                $client->email_contact_id,
                $client->phone_contact_id,
                $client->alternate_phone_contact_id,
            ]))
            ->unique()
            ->values();

        $related = [];

        if ($contactPointIds->isNotEmpty()) {
            $recordsSharingContacts = Client::query()
                ->where(fn ($query) => $query
                    ->whereIn('email_contact_id', $contactPointIds)
                    ->orWhereIn('phone_contact_id', $contactPointIds)
                    ->orWhereIn('alternate_phone_contact_id', $contactPointIds))
                ->get(['id', 'type', 'client_number', 'full_name', 'organization_name', 'email_contact_id', 'phone_contact_id', 'alternate_phone_contact_id']);

            $recordsByContactPoint = [];

            foreach ($recordsSharingContacts as $record) {
                foreach (array_unique(array_filter([
                    $record->email_contact_id,
                    $record->phone_contact_id,
                    $record->alternate_phone_contact_id,
                ])) as $recordContactPointId) {
                    $recordsByContactPoint[$recordContactPointId][] = $record;
                }
            }

            foreach ($clients as $client) {
                $ownContactIds = array_filter([
                    $client->email_contact_id,
                    $client->phone_contact_id,
                    $client->alternate_phone_contact_id,
                ]);

                foreach ($ownContactIds as $contactPointId) {
                    foreach ($recordsByContactPoint[$contactPointId] ?? [] as $record) {
                        if ($record->id === $client->id) {
                            continue;
                        }

                        $related[$client->id][$record->id] = [
                            'id' => $record->id,
                            'name' => $record->full_name ?? $record->organization_name ?? 'Unnamed client',
                            'code' => $record->client_number,
                            'type' => $record->type,
                            'decision' => 'shared_contact',
                        ];
                    }
                }
            }
        }

        $duplicateReviews = ClientDuplicateReview::query()
            ->where('decision', 'duplicate')
            ->where(fn ($query) => $query
                ->whereIn('client_id', $clientIds)
                ->orWhereIn('candidate_client_id', $clientIds))
            ->get(['client_id', 'candidate_client_id']);
        $otherIds = $duplicateReviews
            ->flatMap(fn (ClientDuplicateReview $review): array => [$review->client_id, $review->candidate_client_id])
            ->unique()
            ->values();
        $otherClients = Client::query()->whereIn('id', $otherIds)->get(['id', 'type', 'client_number', 'full_name', 'organization_name'])->keyBy('id');

        foreach ($clients as $client) {
            foreach ($duplicateReviews as $review) {
                $otherId = $review->client_id === $client->id
                    ? $review->candidate_client_id
                    : ($review->candidate_client_id === $client->id ? $review->client_id : null);

                if ($otherId === null) {
                    continue;
                }

                $record = $otherClients->get($otherId);

                if ($record !== null) {
                    $related[$client->id][$otherId] = [
                        'id' => $record->id,
                        'name' => $record->full_name ?? $record->organization_name ?? 'Unnamed client',
                        'code' => $record->client_number,
                        'type' => $record->type,
                        'decision' => 'duplicate',
                    ];
                }
            }
        }

        return collect($related)
            ->map(fn (array $records): array => array_values($records))
            ->all();
    }
}
