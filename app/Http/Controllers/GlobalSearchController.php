<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Matter;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GlobalSearchController extends Controller
{
    /**
     * Search practice records from one authenticated workspace.
     */
    public function index(Request $request): Response
    {
        $query = trim($request->string('query')->toString());

        if ($query !== '') {
            $request->validate(['query' => ['string', 'min:2', 'max:100']]);
        }

        return Inertia::render('search/index', [
            'query' => $query,
            'results' => $query === '' ? [] : $this->resultsFor($query),
        ]);
    }

    /**
     * Return compact result groups for the header type-ahead search.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $query = trim($request->string('query')->toString());
        $request->merge(['query' => $query]);
        $request->validate(['query' => ['required', 'string', 'min:2', 'max:100']]);

        return response()->json(['results' => $this->resultsFor($query, 3)]);
    }

    /**
     * @return array{clients: array<int, array{id: int, title: string, subtitle: string}>, matters: array<int, array{id: int, title: string, subtitle: string}>, invoices: array<int, array{id: int, title: string, subtitle: string}>, documents: array<int, array{id: int, title: string, subtitle: string}>, users: array<int, array{id: int, title: string, subtitle: string}>}
     */
    private function resultsFor(string $query, int $limit = 5): array
    {
        return [
            'clients' => $this->clients($query, $limit),
            'matters' => $this->matters($query, $limit),
            'invoices' => $this->invoices($query, $limit),
            'documents' => $this->documents($query, $limit),
            'users' => $this->users($query, $limit),
        ];
    }

    /** @return array<int, array{id: int, title: string, subtitle: string}> */
    private function clients(string $query, int $limit): array
    {
        return Client::query()
            ->where(fn (Builder $client): Builder => $client
                ->where('client_number', 'like', '%'.$query.'%')
                ->orWhere('full_name', 'like', '%'.$query.'%')
                ->orWhere('organization_name', 'like', '%'.$query.'%')
                ->orWhere('email', 'like', '%'.$query.'%'))
            ->orderBy('organization_name')
            ->orderBy('full_name')
            ->limit($limit)
            ->get()
            ->map(fn (Client $client): array => [
                'id' => $client->id,
                'title' => $client->organization_name ?? $client->full_name ?? $client->client_number,
                'subtitle' => $client->client_number.' · '.($client->email ?? ucfirst($client->type)),
            ])
            ->all();
    }

    /** @return array<int, array{id: int, title: string, subtitle: string}> */
    private function matters(string $query, int $limit): array
    {
        return Matter::query()
            ->with('client:id,full_name,organization_name')
            ->where(fn (Builder $matter): Builder => $matter
                ->where('matter_number', 'like', '%'.$query.'%')
                ->orWhere('title', 'like', '%'.$query.'%'))
            ->latest('opened_at')
            ->limit($limit)
            ->get()
            ->map(fn (Matter $matter): array => [
                'id' => $matter->id,
                'title' => $matter->title,
                'subtitle' => $matter->matter_number.' · '.($matter->client->organization_name ?? $matter->client->full_name ?? 'Client'),
            ])
            ->all();
    }

    /** @return array<int, array{id: int, title: string, subtitle: string}> */
    private function invoices(string $query, int $limit): array
    {
        return Invoice::query()
            ->with('client:id,full_name,organization_name')
            ->where('invoice_number', 'like', '%'.$query.'%')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'title' => $invoice->invoice_number,
                'subtitle' => ($invoice->client->organization_name ?? $invoice->client->full_name ?? 'Client').' · '.ucfirst(str_replace('_', ' ', $invoice->status)),
            ])
            ->all();
    }

    /** @return array<int, array{id: int, title: string, subtitle: string}> */
    private function documents(string $query, int $limit): array
    {
        return Document::query()
            ->where(fn (Builder $document): Builder => $document
                ->where('name', 'like', '%'.$query.'%')
                ->orWhere('original_filename', 'like', '%'.$query.'%'))
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Document $document): array => [
                'id' => $document->id,
                'title' => $document->name,
                'subtitle' => $document->original_filename ?? 'Document',
            ])
            ->all();
    }

    /** @return array<int, array{id: int, title: string, subtitle: string}> */
    private function users(string $query, int $limit): array
    {
        return User::query()
            ->where(fn (Builder $user): Builder => $user
                ->where('name', 'like', '%'.$query.'%')
                ->orWhere('email', 'like', '%'.$query.'%'))
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'title' => $user->name,
                'subtitle' => $user->email,
            ])
            ->all();
    }
}
