<?php

namespace App\Http\Controllers;

use App\Actions\Matters\GenerateMatterNumber;
use App\Http\Requests\OpenMatterRequest;
use App\Models\Client;
use App\Models\Matter;
use App\Models\MatterType;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MatterController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('matters/index', [
            'matters' => Matter::query()
                ->with([
                    'client:id,client_number,full_name,organization_name',
                    'matterType:id,name',
                    'responsibleUser:id,name',
                ])
                ->withCount(['participants', 'attorneys'])
                ->orderByDesc('opened_at')
                ->orderByDesc('id')
                ->get([
                    'id',
                    'matter_number',
                    'client_id',
                    'matter_type_id',
                    'responsible_user_id',
                    'title',
                    'status',
                    'priority',
                    'billing_type',
                    'budget_amount',
                    'opened_at',
                    'deadline_at',
                ])
                ->map(fn (Matter $matter): array => [
                    'id' => $matter->id,
                    'matter_number' => $matter->matter_number,
                    'title' => $matter->title,
                    'client' => $matter->client->full_name
                        ?? $matter->client->organization_name
                        ?? 'Unnamed client',
                    'matter_type' => $matter->matterType->name,
                    'responsible_user' => $matter->responsibleUser->name,
                    'billing_type' => $matter->billing_type,
                    'budget_amount' => $matter->budget_amount,
                    'status' => $matter->status,
                    'priority' => $matter->priority,
                    'opened_at' => $matter->opened_at->toDateString(),
                    'deadline_at' => $matter->deadline_at?->toDateString(),
                    'participants_count' => $matter->participants_count,
                    'attorneys_count' => $matter->attorneys_count,
                ])
                ->all(),
            ...$this->openingData(),
        ]);
    }

    public function open(): RedirectResponse
    {
        return to_route('matters.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function openingData(): array
    {
        return [
            'clients' => Client::query()
                ->where('status', 'active')
                ->get(['id', 'client_number', 'full_name', 'organization_name', 'type'])
                ->map(fn (Client $client): array => [
                    'id' => $client->id,
                    'name' => $client->full_name ?? $client->organization_name ?? 'Unnamed client',
                    'client_number' => $client->client_number,
                    'type' => $client->type,
                ])
                ->sortBy('name')
                ->values(),
            'responsibleUsers' => User::attorneys()
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'matterTypes' => MatterType::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'default_billing_type', 'default_hourly_rate']),
            'today' => now()->toDateString(),
        ];
    }

    public function store(
        OpenMatterRequest $request,
        GenerateMatterNumber $generateMatterNumber,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $generateMatterNumber): void {
            $matter = Matter::create([
                ...Arr::only($request->validated(), [
                    'client_id',
                    'matter_type_id',
                    'responsible_user_id',
                    'title',
                    'description',
                    'status',
                    'priority',
                    'billing_type',
                    'default_hourly_rate',
                    'budget_amount',
                    'opened_at',
                    'deadline_at',
                ]),
                'matter_number' => $generateMatterNumber->handle(),
                'opened_by_user_id' => $request->user()->id,
            ]);

            $matter->attorneys()->sync(array_values(array_unique([
                ...$request->attorneyIds(),
                $matter->responsible_user_id,
            ])));

            $matter->participants()->createMany(
                array_map(
                    fn (array $participant): array => [
                        'role' => $participant['role'],
                        'type' => $participant['type'],
                        'name' => $participant['name'],
                        'organization_name' => $participant['type'] === 'organization'
                            ? $participant['name']
                            : null,
                        'contact_person' => $participant['contact_person'] ?? null,
                        'email' => $participant['email'] ?? null,
                        'phone' => $participant['phone'] ?? null,
                    ],
                    $request->participants(),
                ),
            );
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Matter opened successfully.',
        ]);

        return to_route('matters.index');
    }
}
