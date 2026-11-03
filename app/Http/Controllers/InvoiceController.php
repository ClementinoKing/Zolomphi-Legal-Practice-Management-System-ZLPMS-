<?php

namespace App\Http\Controllers;

use App\Actions\Invoices\SaveInvoiceDraft;
use App\CurrencyConverter;
use App\Http\Requests\SaveInvoiceDraftRequest;
use App\Models\Client;
use App\Models\Document;
use App\Models\Expense;
use App\Models\GeneralSetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Matter;
use App\Models\MatterCharge;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\InvoiceSettings;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceSettings $invoiceSettings) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return Inertia::render('invoices/index', [
            'invoices' => Invoice::query()
                ->where(function ($query) use ($user): void {
                    $query->whereNull('matter_id')
                        ->orWhereHas('matter', fn (Builder $matter): Builder => $matter->whereIn('id', Matter::query()->visibleTo($user)->select('id')));
                })
                ->with([
                    'client:id,client_number,full_name,organization_name',
                    'matter:id,matter_number,title',
                    'creator:id,name',
                ])
                ->latest('created_at')
                ->get([
                    'id',
                    'invoice_number',
                    'client_id',
                    'matter_id',
                    'created_by_user_id',
                    'status',
                    'currency',
                    'issued_at',
                    'due_at',
                    'total_amount',
                    'paid_amount',
                    'balance_due',
                    'created_at',
                ])
                ->map(fn (Invoice $invoice): array => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'client' => $this->clientName($invoice->client),
                    'matter' => $invoice->matter === null
                        ? null
                        : [
                            'matter_number' => $invoice->matter->matter_number,
                            'title' => $invoice->matter->title,
                        ],
                    'status' => $invoice->status,
                    'currency' => $invoice->currency,
                    'issued_at' => $invoice->issued_at?->toDateString(),
                    'due_at' => $invoice->due_at?->toDateString(),
                    'total_amount' => $invoice->total_amount,
                    'paid_amount' => $invoice->paid_amount,
                    'balance_due' => $invoice->balance_due,
                    'created_by' => $invoice->creator?->name,
                    'created_at' => $invoice->created_at?->toDateTimeString(),
                ])
                ->all(),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $client = Client::query()
            ->where('status', 'active')
            ->when(
                ! $user->hasPermission('matters.view.all'),
                fn (Builder $query): Builder => $query->whereHas('matters', fn (Builder $matters): Builder => $matters->whereIn('id', Matter::query()->visibleTo($user)->select('id'))),
            )
            ->whereKey($request->integer('client_id'))
            ->first();
        $matter = $client === null
            ? null
            : $client->matters()->whereKey($request->integer('matter_id'))->first();

        return Inertia::render('invoices/builder', [
            ...$this->builderData($client, $matter, null, $user),
            'invoice' => null,
            'mode' => 'create',
        ]);
    }

    public function store(SaveInvoiceDraftRequest $request, SaveInvoiceDraft $saveInvoiceDraft): RedirectResponse
    {
        $invoice = $saveInvoiceDraft->handle($request->validated(), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Draft invoice '.$invoice->invoice_number.' saved.',
        ]);

        return to_route('invoices.edit', $invoice);
    }

    public function edit(Request $request, Invoice $invoice): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        abort_unless($invoice->status === 'draft', 404);

        $invoice->load([
            'client:id,client_number,full_name,organization_name,status',
            'matter:id,matter_number,title,client_id',
            'items.timeEntry.matter:id,matter_number,title,client_id',
            'items.expense.matter:id,matter_number,title,client_id',
            'items.matterCharge.matter:id,matter_number,title,client_id',
        ]);

        return Inertia::render('invoices/builder', [
            ...$this->builderData($invoice->client, $invoice->matter, $invoice, $user),
            'invoice' => $this->invoicePayload($invoice),
            'mode' => 'edit',
        ]);
    }

    public function printable(Invoice $invoice): ViewContract
    {
        $invoice->load([
            'client.emailContact',
            'client.phoneContact',
            'matter',
            'items',
        ]);

        $settings = $this->invoiceSettings->values();
        $firm = GeneralSetting::query()->first();
        $taxes = $invoice->tax_breakdown;
        $logoId = data_get($settings, 'template.logo.id');
        $logoUrl = is_string($logoId) && str_starts_with($logoId, 'document:')
            ? route('invoices.template-logo', ['document' => (int) substr($logoId, strlen('document:'))])
            : data_get($settings, 'template.logo.preview_url', '/logos/Zolomphi Logo.svg');

        if ($taxes === null) {
            $legacyTaxes = $invoice->items
                ->filter(fn (InvoiceItem $item): bool => (float) $item->tax_rate > 0)
                ->groupBy(fn (InvoiceItem $item): string => (string) $item->tax_rate);
            $taxes = $legacyTaxes->map(fn ($items, string $rate): array => [
                'label' => $settings['tax_label'],
                'rate' => $rate,
                'amount' => number_format((float) $items->sum('tax_amount'), 2, '.', ''),
            ])->values()->all();
        }

        return view('invoices.print', [
            'invoice' => $invoice,
            'client' => $invoice->client,
            'matter' => $invoice->matter,
            'firm' => $firm,
            'settings' => $settings,
            'taxes' => $taxes,
            'logoUrl' => $logoUrl,
            'showTaxId' => $settings['template']['show_tax_id'] && filled($firm?->tax_id),
            'showMatterReference' => $settings['template']['show_matter_reference'] && $invoice->matter !== null,
        ]);
    }

    public function templateLogo(Document $document): HttpResponse
    {
        abort_unless(
            in_array($document->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)
                && $document->category === 'invoice-template-logo',
            404,
        );

        $disk = Storage::disk($document->disk);
        abort_unless($disk->exists($document->path), 404);

        return response($disk->get($document->path), 200, [
            'Content-Type' => $document->mime_type,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function update(
        SaveInvoiceDraftRequest $request,
        Invoice $invoice,
        SaveInvoiceDraft $saveInvoiceDraft,
    ): RedirectResponse {
        $invoice = $saveInvoiceDraft->handle($request->validated(), $request->user(), $invoice);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Draft invoice '.$invoice->invoice_number.' updated.',
        ]);

        return to_route('invoices.edit', $invoice);
    }

    /**
     * @return array<string, mixed>
     */
    private function builderData(?Client $selectedClient, ?Matter $selectedMatter, ?Invoice $invoice, User $user): array
    {
        $currency = $invoice === null
            ? GeneralSetting::query()->value('currency') ?? CurrencyConverter::BaseCurrency
            : $invoice->currency;
        $settings = $this->invoiceSettings->values();

        return [
            'clients' => Client::query()
                ->where('status', 'active')
                ->when(
                    ! $user->hasPermission('clients.view') && ! $user->hasPermission('matters.view.all'),
                    fn (Builder $query): Builder => $query->whereHas('matters', fn (Builder $matters): Builder => $matters->whereIn('id', Matter::query()->visibleTo($user)->select('id'))),
                )
                ->orderByRaw('coalesce(organization_name, full_name)')
                ->get(['id', 'client_number', 'full_name', 'organization_name'])
                ->map(fn (Client $client): array => [
                    'id' => $client->id,
                    'client_number' => $client->client_number,
                    'name' => $this->clientName($client),
                ])
                ->all(),
            'selectedClientId' => $selectedClient?->id,
            'selectedMatterId' => $selectedMatter?->id,
            'matters' => $selectedClient === null
                ? []
                : $selectedClient->matters()
                    ->visibleTo($user)
                    ->orderByDesc('opened_at')
                    ->get(['id', 'matter_number', 'title'])
                    ->map(fn (Matter $matter): array => [
                        'id' => $matter->id,
                        'matter_number' => $matter->matter_number,
                        'title' => $matter->title,
                    ])
                    ->all(),
            'availableSources' => $selectedClient === null
                ? ['time_entries' => [], 'expenses' => [], 'matter_charges' => []]
                : $this->availableSources($selectedClient, $selectedMatter, $invoice, $currency, $settings['tax_enabled'] ? $settings['default_tax_rate'] : '0.00'),
            'defaults' => [
                'currency' => $currency,
                'issued_at' => today()->toDateString(),
                'due_at' => today()->addDays(30)->toDateString(),
                'tax_rate' => '0.00',
                'terms' => $settings['default_terms'],
                'tax_label' => $settings['tax_enabled'] ? $settings['tax_label'] : 'Tax',
                'taxes' => array_values(array_filter([
                    $settings['tax_enabled'] ? [
                        'label' => $settings['tax_label'],
                        'rate' => $settings['default_tax_rate'],
                    ] : null,
                    $settings['levy_enabled'] ? [
                        'label' => $settings['levy_label'],
                        'rate' => $settings['levy_rate'],
                    ] : null,
                ])),
            ],
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function availableSources(
        Client $client,
        ?Matter $matter,
        ?Invoice $invoice,
        string $currency,
        string $defaultTaxRate,
    ): array {
        $converter = app(CurrencyConverter::class);

        return [
            'time_entries' => TimeEntry::query()
                ->with('matter:id,matter_number,title,client_id')
                ->where('is_billable', true)
                ->where('status', 'approved')
                ->whereNotNull('hourly_rate')
                ->whereHas('matter', fn ($query) => $query
                    ->whereBelongsTo($client)
                    ->when($matter !== null, fn ($matterQuery) => $matterQuery->whereKey($matter)))
                ->where(fn (Builder $query): Builder => $this->unbilledOrIncluded($query, $invoice))
                ->orderBy('entry_date')
                ->get(['id', 'matter_id', 'entry_date', 'description', 'hours', 'hourly_rate'])
                ->map(fn (TimeEntry $entry): array => [
                    'id' => $entry->id,
                    'matter_id' => $entry->matter_id,
                    'matter_number' => $entry->matter->matter_number,
                    'matter_title' => $entry->matter->title,
                    'date' => $entry->entry_date->toDateString(),
                    'description' => $entry->description,
                    'quantity' => $entry->hours,
                    'unit_price' => (string) $converter->convert($entry->hourly_rate, CurrencyConverter::BaseCurrency, $currency),
                    'tax_rate' => $defaultTaxRate,
                    'amount' => (string) $converter->convert((float) $entry->hours * (float) $entry->hourly_rate, CurrencyConverter::BaseCurrency, $currency),
                ])
                ->all(),
            'expenses' => Expense::query()
                ->with('matter:id,matter_number,title,client_id')
                ->where('is_billable', true)
                ->whereHas('matter', fn ($query) => $query
                    ->whereBelongsTo($client)
                    ->when($matter !== null, fn ($matterQuery) => $matterQuery->whereKey($matter)))
                ->where(fn (Builder $query): Builder => $this->unbilledOrIncluded($query, $invoice))
                ->orderBy('incurred_at')
                ->get(['id', 'matter_id', 'expense_number', 'category', 'vendor', 'currency', 'amount', 'incurred_at', 'description'])
                ->map(fn (Expense $expense): array => [
                    'id' => $expense->id,
                    'matter_id' => $expense->matter_id,
                    'matter_number' => $expense->matter->matter_number,
                    'matter_title' => $expense->matter->title,
                    'date' => $expense->incurred_at->toDateString(),
                    'description' => $expense->description ?? $expense->category.($expense->vendor === null ? '' : ' · '.$expense->vendor),
                    'quantity' => '1.00',
                    'unit_price' => (string) $converter->convert($expense->amount, $expense->currency, $currency),
                    'tax_rate' => $defaultTaxRate,
                    'amount' => (string) $converter->convert($expense->amount, $expense->currency, $currency),
                ])
                ->all(),
            'matter_charges' => MatterCharge::query()
                ->with('matter:id,matter_number,title,client_id')
                ->where('status', 'billable')
                ->whereHas('matter', fn ($query) => $query
                    ->whereBelongsTo($client)
                    ->when($matter !== null, fn ($matterQuery) => $matterQuery->whereKey($matter)))
                ->where(fn (Builder $query): Builder => $this->unbilledOrIncluded($query, $invoice))
                ->orderBy('charged_at')
                ->get(['id', 'matter_id', 'description', 'quantity', 'unit_price', 'tax_rate', 'amount', 'charged_at'])
                ->map(fn (MatterCharge $charge): array => [
                    'id' => $charge->id,
                    'matter_id' => $charge->matter_id,
                    'matter_number' => $charge->matter->matter_number,
                    'matter_title' => $charge->matter->title,
                    'date' => $charge->charged_at->toDateString(),
                    'description' => $charge->description,
                    'quantity' => $charge->quantity,
                    'unit_price' => (string) $converter->convert($charge->unit_price, CurrencyConverter::BaseCurrency, $currency),
                    'tax_rate' => $charge->tax_rate,
                    'amount' => (string) $converter->convert($charge->amount, CurrencyConverter::BaseCurrency, $currency),
                ])
                ->all(),
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function unbilledOrIncluded(Builder $query, ?Invoice $invoice): Builder
    {
        if ($invoice === null) {
            return $query->whereDoesntHave('invoiceItem');
        }

        return $query->where(function (Builder $invoiceItemQuery) use ($invoice): void {
            $invoiceItemQuery
                ->whereDoesntHave('invoiceItem')
                ->orWhereHas('invoiceItem', fn (Builder $currentInvoiceItemQuery): Builder => $currentInvoiceItemQuery->whereBelongsTo($invoice));
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicePayload(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'client_id' => $invoice->client_id,
            'matter_id' => $invoice->matter_id,
            'currency' => $invoice->currency,
            'issued_at' => $invoice->issued_at?->toDateString(),
            'due_at' => $invoice->due_at?->toDateString(),
            'our_reference' => $invoice->our_reference,
            'your_reference' => $invoice->your_reference,
            'tax_breakdown' => $invoice->tax_breakdown,
            'notes' => $invoice->notes,
            'terms' => $invoice->terms,
            'subtotal' => $invoice->subtotal,
            'tax_amount' => $invoice->tax_amount,
            'total_amount' => $invoice->total_amount,
            'items' => $invoice->items->map(fn (InvoiceItem $item): array => [
                'source_type' => $item->time_entry_id !== null
                    ? 'time_entry'
                    : ($item->expense_id !== null ? 'expense' : ($item->matter_charge_id !== null ? 'matter_charge' : 'manual')),
                'source_id' => $item->time_entry_id ?? $item->expense_id ?? $item->matter_charge_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'tax_rate' => $item->tax_rate,
            ])->all(),
        ];
    }

    private function clientName(Client $client): string
    {
        return $client->organization_name ?? $client->full_name ?? $client->client_number;
    }
}
