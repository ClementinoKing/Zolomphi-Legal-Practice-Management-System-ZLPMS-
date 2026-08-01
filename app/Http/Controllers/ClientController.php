<?php

namespace App\Http\Controllers;

use App\Actions\Clients\GenerateClientNumber;
use App\Http\Requests\ClientRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('clients/index', [
            'clients' => Client::query()
                ->orderBy('name')
                ->get([
                    'id',
                    'type',
                    'full_name',
                    'organization_name',
                    'client_number',
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
                ])
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
                    'is_active' => $client->status === 'active',
                ])
                ->all(),
        ]);
    }

    public function store(
        ClientRequest $request,
        GenerateClientNumber $generateClientNumber,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $generateClientNumber): void {
            $attributes = $this->clientAttributes($request);
            $attributes['client_number'] = $generateClientNumber->handle();
            Client::create($attributes);
        });

        return $this->success('Client created.');
    }

    public function update(
        ClientRequest $request,
        Client $client,
    ): RedirectResponse {
        $client->update($this->clientAttributes($request));

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
}
