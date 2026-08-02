<?php

namespace App\Actions\Invoices;

use App\Actions\Audit\RecordAuditEvent;
use App\Actions\Notifications\SendPracticeAlert;
use App\CurrencyConverter;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Matter;
use App\Models\MatterCharge;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveInvoiceDraft
{
    public function __construct(
        private readonly CurrencyConverter $currencyConverter,
        private readonly RecordAuditEvent $audit,
        private readonly SendPracticeAlert $alerts,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes, User $user, ?Invoice $invoice = null): Invoice
    {
        if (! $this->currencyConverter->canConvert(CurrencyConverter::BaseCurrency, $attributes['currency'])) {
            throw ValidationException::withMessages([
                'currency' => 'The exchange rate for this currency is currently unavailable. Please try again shortly.',
            ]);
        }

        return DB::transaction(function () use ($attributes, $user, $invoice): Invoice {
            if ($invoice !== null && ! $user->can('update', $invoice)) {
                throw new AuthorizationException;
            }

            $client = Client::query()->whereKey((int) $attributes['client_id'])->firstOrFail();
            if (! $user->can('view', $client)) {
                throw new AuthorizationException;
            }

            $matter = $this->matterFor($attributes, $client, $user);
            $draft = $this->saveInvoice($attributes, $user, $invoice, $client, $matter);
            $items = $this->buildItems($attributes['items'], $client, $matter, $draft, $user);

            $draft->items()->delete();
            $draft->items()->createMany($items);

            $totals = $this->totals($items);
            $draft->update($totals);
            $this->recordActivity($draft, $user, $invoice === null ? 'invoice.created' : 'invoice.updated');

            if ($invoice === null && $matter !== null && $matter->responsible_user_id !== null) {
                $this->alerts->handle(
                    User::query()->whereKey($matter->responsible_user_id)->get(),
                    $user,
                    [
                        'title' => 'Invoice draft created',
                        'body' => 'A draft invoice was created for '.$matter->matter_number.'.',
                        'url' => route('invoices.index'),
                        'type' => 'invoice',
                    ],
                );
            }

            return $draft->fresh(['client', 'matter', 'items.timeEntry.matter', 'items.expense.matter', 'items.matterCharge.matter']);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function matterFor(array $attributes, Client $client, User $user): ?Matter
    {
        $matterId = $attributes['matter_id'] ?? null;

        if ($matterId === null) {
            return null;
        }

        $matter = Matter::query()
            ->whereBelongsTo($client)
            ->whereKey((int) $matterId)
            ->first();

        if ($matter === null) {
            throw ValidationException::withMessages([
                'matter_id' => 'Select a matter that belongs to the invoice client.',
            ]);
        }

        if (! $user->can('view', $matter)) {
            throw new AuthorizationException;
        }

        return $matter;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function saveInvoice(
        array $attributes,
        User $user,
        ?Invoice $invoice,
        Client $client,
        ?Matter $matter,
    ): Invoice {
        if ($invoice !== null) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => 'Only draft invoices can be changed.',
                ]);
            }
        }

        $data = [
            'client_id' => $client->id,
            'matter_id' => $matter?->id,
            'status' => 'draft',
            'currency' => $attributes['currency'],
            'issued_at' => $attributes['issued_at'],
            'due_at' => $attributes['due_at'],
            'notes' => $attributes['notes'] ?? null,
            'terms' => $attributes['terms'] ?? null,
        ];

        if ($invoice === null) {
            return Invoice::query()->create([
                ...$data,
                'invoice_number' => app(GenerateInvoiceNumber::class)->handle(),
                'created_by_user_id' => $user->id,
            ]);
        }

        $invoice->update($data);

        return $invoice;
    }

    /**
     * @param  array<int, array<string, mixed>>  $requestedItems
     * @return array<int, array<string, int|float|string|null>>
     */
    private function buildItems(
        array $requestedItems,
        Client $client,
        ?Matter $matter,
        Invoice $invoice,
        User $user,
    ): array {
        $sources = $this->sourcesFor($requestedItems, $client, $matter, $user);
        $errors = [];
        $seenSources = [];
        $occupiedSources = $this->occupiedSources($requestedItems, $invoice);
        $items = [];

        foreach ($requestedItems as $index => $requestedItem) {
            $sourceType = $requestedItem['source_type'];
            $sourceId = $requestedItem['source_id'] ?? null;

            if ($sourceType === 'manual') {
                $items[] = $this->line(
                    $requestedItem['description'],
                    (float) $requestedItem['quantity'],
                    (float) $requestedItem['unit_price'],
                    (float) $requestedItem['tax_rate'],
                    $index,
                );

                continue;
            }

            $sourceKey = $sourceType.':'.$sourceId;

            if (isset($seenSources[$sourceKey])) {
                $errors["items.$index.source_id"] = 'A billable record can only be added once.';

                continue;
            }

            $seenSources[$sourceKey] = true;

            if (! isset($sources[$sourceType][$sourceId])) {
                $errors["items.$index.source_id"] = 'This billing record is unavailable or no longer eligible.';

                continue;
            }

            if (isset($occupiedSources[$sourceKey])) {
                $errors["items.$index.source_id"] = 'This billing record is already on another invoice.';

                continue;
            }

            $items[] = $this->sourceLine(
                $sourceType,
                $sources[$sourceType][$sourceId],
                $requestedItem['description'],
                $index,
                $invoice->currency,
            );
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $items;
    }

    /**
     * @param  array<int, array<string, mixed>>  $requestedItems
     * @return array<string, array<int, Expense|MatterCharge|TimeEntry>>
     */
    private function sourcesFor(array $requestedItems, Client $client, ?Matter $matter, User $user): array
    {
        $ids = [
            'time_entry' => [],
            'expense' => [],
            'matter_charge' => [],
        ];

        foreach ($requestedItems as $item) {
            if ($item['source_type'] !== 'manual') {
                $ids[$item['source_type']][] = $item['source_id'];
            }
        }

        return [
            'time_entry' => TimeEntry::query()
                ->whereIn('id', array_unique($ids['time_entry']))
                ->where('is_billable', true)
                ->where('status', 'approved')
                ->whereNotNull('hourly_rate')
                ->whereHas('matter', fn ($query) => $query
                    ->whereBelongsTo($client)
                    ->whereIn('id', Matter::query()->visibleTo($user)->select('id'))
                    ->when($matter !== null, fn ($matterQuery) => $matterQuery->whereKey($matter)))
                ->lockForUpdate()
                ->get()
                ->keyBy('id')
                ->all(),
            'expense' => Expense::query()
                ->whereIn('id', array_unique($ids['expense']))
                ->where('is_billable', true)
                ->whereHas('matter', fn ($query) => $query
                    ->whereBelongsTo($client)
                    ->whereIn('id', Matter::query()->visibleTo($user)->select('id'))
                    ->when($matter !== null, fn ($matterQuery) => $matterQuery->whereKey($matter)))
                ->lockForUpdate()
                ->get()
                ->keyBy('id')
                ->all(),
            'matter_charge' => MatterCharge::query()
                ->whereIn('id', array_unique($ids['matter_charge']))
                ->where('status', 'billable')
                ->whereHas('matter', fn ($query) => $query
                    ->whereBelongsTo($client)
                    ->whereIn('id', Matter::query()->visibleTo($user)->select('id'))
                    ->when($matter !== null, fn ($matterQuery) => $matterQuery->whereKey($matter)))
                ->lockForUpdate()
                ->get()
                ->keyBy('id')
                ->all(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $requestedItems
     * @return array<string, true>
     */
    private function occupiedSources(array $requestedItems, Invoice $invoice): array
    {
        $ids = [
            'time_entry' => [],
            'expense' => [],
            'matter_charge' => [],
        ];

        foreach ($requestedItems as $item) {
            if ($item['source_type'] !== 'manual') {
                $ids[$item['source_type']][] = $item['source_id'];
            }
        }

        $existingItems = InvoiceItem::query()
            ->where('invoice_id', '!=', $invoice->id)
            ->where(function (Builder $query) use ($ids): void {
                $query
                    ->whereIn('time_entry_id', array_unique($ids['time_entry']))
                    ->orWhereIn('expense_id', array_unique($ids['expense']))
                    ->orWhereIn('matter_charge_id', array_unique($ids['matter_charge']));
            })
            ->get(['time_entry_id', 'expense_id', 'matter_charge_id']);

        $occupied = [];

        foreach ($existingItems as $existingItem) {
            foreach (['time_entry', 'expense', 'matter_charge'] as $sourceType) {
                $column = $sourceType.'_id';

                if ($existingItem->{$column} !== null) {
                    $occupied[$sourceType.':'.$existingItem->{$column}] = true;
                }
            }
        }

        return $occupied;
    }

    /**
     * @return array<string, int|float|string|null>
     */
    private function sourceLine(
        string $sourceType,
        Expense|MatterCharge|TimeEntry $source,
        string $description,
        int $sortOrder,
        string $currency,
    ): array {
        if ($sourceType === 'time_entry' && $source instanceof TimeEntry) {
            return $this->line(
                $description,
                (float) $source->hours,
                $this->currencyConverter->convert((float) $source->hourly_rate, CurrencyConverter::BaseCurrency, $currency),
                0,
                $sortOrder,
                timeEntryId: $source->id,
            );
        }

        if ($sourceType === 'expense' && $source instanceof Expense) {
            return $this->line(
                $description,
                1,
                $this->currencyConverter->convert((float) $source->amount, $source->currency, $currency),
                0,
                $sortOrder,
                expenseId: $source->id,
            );
        }

        if ($sourceType === 'matter_charge' && $source instanceof MatterCharge) {
            return $this->line(
                $description,
                (float) $source->quantity,
                $this->currencyConverter->convert((float) $source->unit_price, CurrencyConverter::BaseCurrency, $currency),
                (float) $source->tax_rate,
                $sortOrder,
                matterChargeId: $source->id,
            );
        }

        throw new \LogicException('The requested invoice source type does not match its record.');
    }

    /**
     * @return array<string, int|float|string|null>
     */
    private function line(
        string $description,
        float $quantity,
        float $unitPrice,
        float $taxRate,
        int $sortOrder,
        ?int $timeEntryId = null,
        ?int $expenseId = null,
        ?int $matterChargeId = null,
    ): array {
        $subtotal = round($quantity * $unitPrice, 2);
        $taxAmount = round($subtotal * ($taxRate / 100), 2);

        return [
            'time_entry_id' => $timeEntryId,
            'expense_id' => $expenseId,
            'matter_charge_id' => $matterChargeId,
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'amount' => round($subtotal + $taxAmount, 2),
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * @param  array<int, array<string, int|float|string|null>>  $items
     * @return array<string, float>
     */
    private function totals(array $items): array
    {
        $subtotal = array_sum(array_map(
            fn (array $item): float => round((float) $item['quantity'] * (float) $item['unit_price'], 2),
            $items,
        ));
        $taxAmount = array_sum(array_map(
            fn (array $item): float => (float) $item['tax_amount'],
            $items,
        ));
        $total = round($subtotal + $taxAmount, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'tax_amount' => round($taxAmount, 2),
            'discount_amount' => 0,
            'total_amount' => $total,
            'balance_due' => $total,
        ];
    }

    private function recordActivity(Invoice $invoice, User $user, string $event): void
    {
        $this->audit->handle(
            user: $user,
            event: $event,
            description: $event === 'invoice.created'
                ? 'Created draft invoice '.$invoice->invoice_number.'.'
                : 'Updated draft invoice '.$invoice->invoice_number.'.',
            subject: $invoice,
            clientId: $invoice->client_id,
            matterId: $invoice->matter_id,
            properties: [
                'invoice_number' => $invoice->invoice_number,
                'item_count' => $invoice->items()->count(),
                'total_amount' => $invoice->total_amount,
            ],
        );
    }
}
