import { Link, router, useForm } from '@inertiajs/react';
import { CircleAlert, FilePlus2, Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { create, index, store, update } from '@/routes/invoices';

type SourceType = 'time_entry' | 'expense' | 'matter_charge' | 'manual';

type InvoiceLine = {
    source_type: SourceType;
    source_id: number | null;
    description: string;
    quantity: string;
    unit_price: string;
    tax_rate: string;
};

type Invoice = {
    id: number;
    invoice_number: string;
    client_id: number;
    matter_id: number | null;
    currency: string;
    issued_at: string;
    due_at: string;
    notes: string | null;
    terms: string | null;
    subtotal: string;
    tax_amount: string;
    total_amount: string;
    items: InvoiceLine[];
};

type Source = {
    id: number;
    matter_id: number;
    matter_number: string;
    matter_title: string;
    date: string;
    description: string;
    quantity: string;
    unit_price: string;
    tax_rate: string;
    amount: string;
};

type FormData = {
    client_id: number | null;
    matter_id: number | null;
    currency: string;
    issued_at: string;
    due_at: string;
    notes: string;
    terms: string;
    items: InvoiceLine[];
};

type Props = {
    mode: 'create' | 'edit';
    invoice: Invoice | null;
    clients: Array<{ id: number; client_number: string; name: string }>;
    selectedClientId: number | null;
    selectedMatterId: number | null;
    matters: Array<{ id: number; matter_number: string; title: string }>;
    availableSources: {
        time_entries: Source[];
        expenses: Source[];
        matter_charges: Source[];
    };
    defaults: {
        currency: string;
        issued_at: string;
        due_at: string;
        tax_rate: string;
        terms: string;
        tax_label: string;
    };
};

const sourceLabels: Record<Exclude<SourceType, 'manual'>, string> = {
    time_entry: 'Approved time',
    expense: 'Billable expense',
    matter_charge: 'Flat fee',
};

export default function InvoiceBuilder(props: Props) {
    const { defaults, invoice, selectedClientId, selectedMatterId } = props;
    const formData = useMemo(
        () =>
            initialData(invoice, selectedClientId, selectedMatterId, defaults),
        [defaults, invoice, selectedClientId, selectedMatterId],
    );
    const form = useForm<FormData>(formData);
    const { clearErrors, setData } = form;

    useEffect(() => {
        setData(formData);
        clearErrors();
    }, [clearErrors, formData, setData]);

    const totals = useMemo(
        () => calculateTotals(form.data.items),
        [form.data.items],
    );
    const hasSelectedClient = form.data.client_id !== null;

    function changeContext(
        clientId: number | null,
        matterId: number | null,
    ): void {
        if (props.mode === 'edit') {
            return;
        }

        router.get(
            create().url,
            {
                ...(clientId === null ? {} : { client_id: clientId }),
                ...(matterId === null ? {} : { matter_id: matterId }),
            },
            { preserveScroll: true },
        );
    }

    function addSource(
        sourceType: Exclude<SourceType, 'manual'>,
        source: Source,
    ): void {
        if (
            form.data.items.some(
                (line) =>
                    line.source_type === sourceType &&
                    line.source_id === source.id,
            )
        ) {
            return;
        }

        form.setData('items', [
            ...form.data.items,
            {
                source_type: sourceType,
                source_id: source.id,
                description: source.description,
                quantity: source.quantity,
                unit_price: source.unit_price,
                tax_rate: source.tax_rate,
            },
        ]);
    }

    function addManualLine(): void {
        form.setData('items', [
            ...form.data.items,
            {
                source_type: 'manual',
                source_id: null,
                description: '',
                quantity: '1',
                unit_price: '0',
                tax_rate: defaults.tax_rate,
            },
        ]);
    }

    function updateLine(
        index: number,
        field: keyof InvoiceLine,
        value: string,
    ): void {
        form.setData(
            'items',
            form.data.items.map((line, lineIndex) =>
                lineIndex === index ? { ...line, [field]: value } : line,
            ),
        );
    }

    function removeLine(index: number): void {
        form.setData(
            'items',
            form.data.items.filter((_, lineIndex) => lineIndex !== index),
        );
    }

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        if (props.mode === 'create') {
            form.post(store().url, { preserveScroll: true });

            return;
        }

        if (props.invoice !== null) {
            form.patch(update(props.invoice.id).url, { preserveScroll: true });
        }
    }

    return (
        <form onSubmit={submit} className="grid gap-6">
            <header className="flex flex-col gap-4 border-b pb-5 lg:flex-row lg:items-start lg:justify-between">
                <div className="grid gap-1">
                    <Link
                        href={index()}
                        className="mb-2 text-sm text-muted-foreground hover:text-foreground"
                    >
                        ← All invoices
                    </Link>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge className="border-amber-200 bg-amber-100 text-amber-800 dark:border-amber-900/70 dark:bg-amber-950/50 dark:text-amber-300">
                            Draft
                        </Badge>
                        {props.invoice !== null && (
                            <span className="font-mono text-xs text-muted-foreground">
                                {props.invoice.invoice_number}
                            </span>
                        )}
                    </div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {props.mode === 'create'
                            ? 'Build invoice'
                            : `Edit ${props.invoice?.invoice_number}`}
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Select the client and eligible work, then review the
                        invoice line items before saving the draft.
                    </p>
                </div>
                <Button
                    type="submit"
                    disabled={
                        form.processing ||
                        !hasSelectedClient ||
                        form.data.items.length === 0
                    }
                >
                    {form.processing && <Spinner />}
                    Save draft
                </Button>
            </header>

            {Object.keys(form.errors).length > 0 && (
                <Alert variant="destructive">
                    <CircleAlert />
                    <AlertTitle>Review the invoice details</AlertTitle>
                    <AlertDescription>
                        Correct the highlighted fields before saving this draft.
                    </AlertDescription>
                </Alert>
            )}

            <section className="grid gap-4 rounded-xl border border-border/70 bg-background p-4 md:grid-cols-2 lg:grid-cols-4">
                <Field label="Client" error={form.errors.client_id}>
                    <select
                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs disabled:cursor-not-allowed disabled:opacity-60"
                        value={form.data.client_id ?? ''}
                        disabled={props.mode === 'edit'}
                        onChange={(event) => {
                            const clientId =
                                event.target.value === ''
                                    ? null
                                    : Number(event.target.value);
                            form.setData('client_id', clientId);
                            form.setData('matter_id', null);
                            form.setData('items', []);
                            changeContext(clientId, null);
                        }}
                    >
                        <option value="">Select client</option>
                        {props.clients.map((client) => (
                            <option key={client.id} value={client.id}>
                                {client.name} · {client.client_number}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field label="Matter (optional)" error={form.errors.matter_id}>
                    <select
                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs disabled:cursor-not-allowed disabled:opacity-60"
                        value={form.data.matter_id ?? ''}
                        disabled={!hasSelectedClient || props.mode === 'edit'}
                        onChange={(event) => {
                            const matterId =
                                event.target.value === ''
                                    ? null
                                    : Number(event.target.value);
                            form.setData('matter_id', matterId);
                            form.setData('items', []);
                            changeContext(form.data.client_id, matterId);
                        }}
                    >
                        <option value="">All client matters</option>
                        {props.matters.map((matter) => (
                            <option key={matter.id} value={matter.id}>
                                {matter.matter_number} · {matter.title}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field label="Invoice date" error={form.errors.issued_at}>
                    <Input
                        type="date"
                        value={form.data.issued_at}
                        onChange={(event) =>
                            form.setData('issued_at', event.target.value)
                        }
                    />
                </Field>
                <Field label="Due date" error={form.errors.due_at}>
                    <Input
                        type="date"
                        value={form.data.due_at}
                        onChange={(event) =>
                            form.setData('due_at', event.target.value)
                        }
                    />
                </Field>
            </section>

            <section className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div className="grid gap-5">
                    <section className="grid gap-4 rounded-xl border border-border/70 bg-background p-4">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 className="font-semibold">Eligible work</h2>
                                <p className="text-sm text-muted-foreground">
                                    Only work that has not already been invoiced
                                    appears here.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addManualLine}
                                disabled={!hasSelectedClient}
                            >
                                <Plus /> Add manual line
                            </Button>
                        </div>
                        {!hasSelectedClient ? (
                            <EmptyWorkState />
                        ) : (
                            <div className="grid gap-3">
                                <SourceSection
                                    title="Approved time entries"
                                    type="time_entry"
                                    currency={form.data.currency}
                                    sources={
                                        props.availableSources.time_entries
                                    }
                                    lines={form.data.items}
                                    onAdd={addSource}
                                />
                                <SourceSection
                                    title="Billable expenses"
                                    type="expense"
                                    currency={form.data.currency}
                                    sources={props.availableSources.expenses}
                                    lines={form.data.items}
                                    onAdd={addSource}
                                />
                                <SourceSection
                                    title="Billable flat fees"
                                    type="matter_charge"
                                    currency={form.data.currency}
                                    sources={
                                        props.availableSources.matter_charges
                                    }
                                    lines={form.data.items}
                                    onAdd={addSource}
                                />
                            </div>
                        )}
                    </section>

                    <section className="grid gap-4 rounded-xl border border-border/70 bg-background p-4">
                        <div>
                            <h2 className="font-semibold">Invoice items</h2>
                            <p className="text-sm text-muted-foreground">
                                Source amounts are fixed from the approved work
                                record. Manual lines are editable.
                            </p>
                        </div>
                        {form.data.items.length === 0 ? (
                            <div className="rounded-lg border border-dashed px-4 py-10 text-center text-sm text-muted-foreground">
                                Add approved work, a billable expense, a flat
                                fee, or a manual line to start the invoice.
                            </div>
                        ) : (
                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full min-w-220 text-left text-sm">
                                    <thead className="bg-muted/50 text-xs text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-3 py-3 font-medium">
                                                Description
                                            </th>
                                            <th className="px-3 py-3 font-medium">
                                                Qty
                                            </th>
                                            <th className="px-3 py-3 font-medium">
                                                Unit price
                                            </th>
                                            <th className="px-3 py-3 font-medium">
                                                {defaults.tax_label}
                                            </th>
                                            <th className="px-3 py-3 text-right font-medium">
                                                Amount
                                            </th>
                                            <th className="px-3 py-3">
                                                <span className="sr-only">
                                                    Remove
                                                </span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {form.data.items.map(
                                            (line, lineIndex) => {
                                                const isManual =
                                                    line.source_type ===
                                                    'manual';
                                                const lineTotal =
                                                    lineAmount(line);

                                                return (
                                                    <tr
                                                        key={`${line.source_type}-${line.source_id ?? 'manual'}-${lineIndex}`}
                                                    >
                                                        <td className="min-w-80 px-3 py-3 align-top">
                                                            <div className="grid gap-1">
                                                                {line.source_type !==
                                                                    'manual' && (
                                                                    <div className="flex items-center gap-2">
                                                                        <Badge variant="outline">
                                                                            {
                                                                                sourceLabels[
                                                                                    line
                                                                                        .source_type
                                                                                ]
                                                                            }
                                                                        </Badge>
                                                                    </div>
                                                                )}
                                                                <Input
                                                                    aria-label={`Description for line ${lineIndex + 1}`}
                                                                    value={
                                                                        line.description
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        updateLine(
                                                                            lineIndex,
                                                                            'description',
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                />
                                                                <LineError
                                                                    error={
                                                                        form
                                                                            .errors[
                                                                            `items.${lineIndex}.description`
                                                                        ]
                                                                    }
                                                                />
                                                            </div>
                                                        </td>
                                                        <td className="w-24 px-3 py-3 align-top">
                                                            <Input
                                                                type="number"
                                                                min="0.01"
                                                                step="0.01"
                                                                value={
                                                                    line.quantity
                                                                }
                                                                disabled={
                                                                    !isManual
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateLine(
                                                                        lineIndex,
                                                                        'quantity',
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    )
                                                                }
                                                            />
                                                            <LineError
                                                                error={
                                                                    form.errors[
                                                                        `items.${lineIndex}.quantity`
                                                                    ]
                                                                }
                                                            />
                                                        </td>
                                                        <td className="w-32 px-3 py-3 align-top">
                                                            <Input
                                                                type="number"
                                                                min="0"
                                                                step="0.01"
                                                                value={
                                                                    line.unit_price
                                                                }
                                                                disabled={
                                                                    !isManual
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateLine(
                                                                        lineIndex,
                                                                        'unit_price',
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    )
                                                                }
                                                            />
                                                            <LineError
                                                                error={
                                                                    form.errors[
                                                                        `items.${lineIndex}.unit_price`
                                                                    ]
                                                                }
                                                            />
                                                        </td>
                                                        <td className="w-24 px-3 py-3 align-top">
                                                            <Input
                                                                type="number"
                                                                min="0"
                                                                max="100"
                                                                step="0.01"
                                                                value={
                                                                    line.tax_rate
                                                                }
                                                                disabled={
                                                                    !isManual
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateLine(
                                                                        lineIndex,
                                                                        'tax_rate',
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    )
                                                                }
                                                            />
                                                            <LineError
                                                                error={
                                                                    form.errors[
                                                                        `items.${lineIndex}.tax_rate`
                                                                    ]
                                                                }
                                                            />
                                                        </td>
                                                        <td className="w-32 px-3 py-3 text-right align-top font-medium">
                                                            {money(
                                                                lineTotal,
                                                                form.data
                                                                    .currency,
                                                            )}
                                                        </td>
                                                        <td className="px-3 py-3 text-right align-top">
                                                            <Button
                                                                type="button"
                                                                size="icon"
                                                                variant="ghost"
                                                                onClick={() =>
                                                                    removeLine(
                                                                        lineIndex,
                                                                    )
                                                                }
                                                                aria-label={`Remove invoice item ${lineIndex + 1}`}
                                                            >
                                                                <Trash2 className="text-destructive" />
                                                            </Button>
                                                        </td>
                                                    </tr>
                                                );
                                            },
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>

                    <section className="grid gap-4 rounded-xl border border-border/70 bg-background p-4 md:grid-cols-2">
                        <Field label="Notes" error={form.errors.notes}>
                            <textarea
                                className="min-h-28 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                                placeholder="Optional message for the client"
                            />
                        </Field>
                        <Field label="Terms" error={form.errors.terms}>
                            <textarea
                                className="min-h-28 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                value={form.data.terms}
                                onChange={(event) =>
                                    form.setData('terms', event.target.value)
                                }
                                placeholder="Optional payment terms"
                            />
                        </Field>
                    </section>
                </div>

                <aside className="h-fit rounded-xl border border-border/70 bg-background p-4 lg:sticky lg:top-4">
                    <p className="text-sm font-medium text-muted-foreground">
                        Draft total
                    </p>
                    <p className="mt-2 text-3xl font-semibold tracking-tight">
                        {money(totals.total, form.data.currency)}
                    </p>
                    <dl className="mt-5 grid gap-3 border-t pt-4 text-sm">
                        <SummaryRow
                            label="Subtotal"
                            value={money(totals.subtotal, form.data.currency)}
                        />
                        <SummaryRow
                            label={defaults.tax_label}
                            value={money(totals.tax, form.data.currency)}
                        />
                        <SummaryRow
                            label="Balance due"
                            value={money(totals.total, form.data.currency)}
                            strong
                        />
                    </dl>
                    <div className="mt-5 grid gap-2 border-t pt-4">
                        <div className="grid gap-1 text-sm">
                            <span className="font-medium">
                                Invoice currency
                            </span>
                            <span className="rounded-md border border-input bg-muted/30 px-3 py-2 font-mono text-sm">
                                {form.data.currency}
                            </span>
                            <p className="text-xs text-muted-foreground">
                                New drafts use the firm display currency.
                            </p>
                        </div>
                        <LineError error={form.errors.currency} />
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                !hasSelectedClient ||
                                form.data.items.length === 0
                            }
                        >
                            {form.processing && <Spinner />}
                            Save draft
                        </Button>
                    </div>
                </aside>
            </section>
        </form>
    );
}

function SourceSection({
    title,
    type,
    currency,
    sources,
    lines,
    onAdd,
}: {
    title: string;
    type: Exclude<SourceType, 'manual'>;
    currency: string;
    sources: Source[];
    lines: InvoiceLine[];
    onAdd: (type: Exclude<SourceType, 'manual'>, source: Source) => void;
}) {
    return (
        <div className="rounded-lg border">
            <div className="flex items-center justify-between gap-3 border-b bg-muted/20 px-3 py-2">
                <h3 className="text-sm font-medium">{title}</h3>
                <span className="text-xs text-muted-foreground">
                    {sources.length} available
                </span>
            </div>
            {sources.length === 0 ? (
                <p className="px-3 py-4 text-sm text-muted-foreground">
                    No eligible records.
                </p>
            ) : (
                <div className="divide-y">
                    {sources.map((source) => {
                        const added = lines.some(
                            (line) =>
                                line.source_type === type &&
                                line.source_id === source.id,
                        );

                        return (
                            <div
                                key={source.id}
                                className="flex flex-col gap-3 px-3 py-3 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium">
                                        {source.description}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {source.matter_number} ·{' '}
                                        {source.matter_title} · {source.date}
                                    </p>
                                </div>
                                <div className="flex shrink-0 items-center gap-3 sm:flex-col sm:items-end sm:gap-1">
                                    <span className="text-sm font-medium">
                                        {money(source.amount, currency)}
                                    </span>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={added}
                                        onClick={() => onAdd(type, source)}
                                    >
                                        {added ? 'Added' : 'Add'}
                                    </Button>
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

function EmptyWorkState() {
    return (
        <div className="flex min-h-36 flex-col items-center justify-center gap-2 rounded-lg border border-dashed px-4 text-center text-sm text-muted-foreground">
            <FilePlus2 className="size-5" />
            Select a client to load approved and billable work.
        </div>
    );
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <label className="grid gap-1.5 text-sm font-medium">
            {label}
            {children}
            <LineError error={error} />
        </label>
    );
}

function LineError({ error }: { error?: string }) {
    return error ? (
        <span className="text-xs font-normal text-destructive">{error}</span>
    ) : null;
}

function SummaryRow({
    label,
    value,
    strong = false,
}: {
    label: string;
    value: string;
    strong?: boolean;
}) {
    return (
        <div
            className={
                strong
                    ? 'flex items-center justify-between font-semibold text-foreground'
                    : 'flex items-center justify-between text-muted-foreground'
            }
        >
            <dt>{label}</dt>
            <dd>{value}</dd>
        </div>
    );
}

function initialData(
    invoice: Invoice | null,
    selectedClientId: number | null,
    selectedMatterId: number | null,
    defaults: Props['defaults'],
): FormData {
    return {
        client_id: invoice?.client_id ?? selectedClientId,
        matter_id: invoice?.matter_id ?? selectedMatterId,
        currency: invoice?.currency ?? defaults.currency,
        issued_at: invoice?.issued_at ?? defaults.issued_at,
        due_at: invoice?.due_at ?? defaults.due_at,
        notes: invoice?.notes ?? '',
        terms: invoice?.terms ?? defaults.terms,
        items: invoice?.items ?? [],
    };
}

function calculateTotals(lines: InvoiceLine[]): {
    subtotal: number;
    tax: number;
    total: number;
} {
    return lines.reduce(
        (totals, line) => {
            const subtotal =
                Number(line.quantity || 0) * Number(line.unit_price || 0);
            const tax = subtotal * (Number(line.tax_rate || 0) / 100);

            return {
                subtotal: totals.subtotal + subtotal,
                tax: totals.tax + tax,
                total: totals.total + subtotal + tax,
            };
        },
        { subtotal: 0, tax: 0, total: 0 },
    );
}

function lineAmount(line: InvoiceLine): number {
    const subtotal = Number(line.quantity || 0) * Number(line.unit_price || 0);

    return subtotal + subtotal * (Number(line.tax_rate || 0) / 100);
}

function money(value: string | number, currency: string): string {
    return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: currency || 'MWK',
        maximumFractionDigits: 2,
    }).format(Number(value));
}
