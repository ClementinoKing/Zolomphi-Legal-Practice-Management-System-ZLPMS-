<?php

namespace App\Http\Controllers;

use App\Actions\Matters\GenerateMatterNumber;
use App\Http\Requests\OpenMatterRequest;
use App\Models\Client;
use App\Models\GeneralSetting;
use App\Models\Matter;
use App\Models\MatterType;
use App\Models\User;
use App\Services\PracticeRecordSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MatterController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return Inertia::render('matters/index', [
            'matters' => Matter::query()
                ->visibleTo($user)
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
                    'metadata',
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
            ...$this->openingData($user),
        ]);
    }

    public function open(): RedirectResponse
    {
        return to_route('matters.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function openingData(User $user): array
    {
        return [
            'clients' => $user->hasPermission('clients.view')
                ? Client::query()
                    ->where('status', 'active')
                    ->get(['id', 'client_number', 'full_name', 'organization_name', 'type'])
                    ->map(fn (Client $client): array => [
                        'id' => $client->id,
                        'name' => $client->full_name ?? $client->organization_name ?? 'Unnamed client',
                        'client_number' => $client->client_number,
                        'type' => $client->type,
                    ])
                    ->sortBy('name')
                    ->values()
                : [],
            'responsibleUsers' => User::attorneys()
                ->when(
                    ! $user->hasPermission('matters.participants.manage'),
                    fn (Builder $query): Builder => $query->whereKey($user->id),
                )
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'matterTypes' => MatterType::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'default_billing_type', 'default_hourly_rate']),
            'today' => now()->toDateString(),
            'customFields' => app(PracticeRecordSettings::class)->matterValues(GeneralSetting::query()->first())['fields'],
        ];
    }

    public function store(
        OpenMatterRequest $request,
        GenerateMatterNumber $generateMatterNumber,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $generateMatterNumber): void {
            $matterType = MatterType::query()->findOrFail($request->integer('matter_type_id'));
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
                'matter_number' => $generateMatterNumber->handle($matterType),
                'opened_by_user_id' => $request->user()->id,
                'metadata' => ['custom_fields' => $request->customFields()],
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
