import { Head, useForm } from '@inertiajs/react';
import {
    Check,
    FileText,
    Palette,
    ReceiptText,
    Save,
    Settings2,
    Upload,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { index as invoices } from '@/routes/invoices';
import { edit, update } from '@/routes/invoices/settings';

type InvoiceTemplate = {
    layout: 'classic' | 'modern' | 'minimal';
    accent_color: string;
    header_note: string;
    footer_note: string;
    show_tax_id: boolean;
    show_matter_reference: boolean;
    logo: InvoiceLogo | null;
};

type InvoiceLogo = {
    id: string;
    name: string;
    source: string;
    preview_url: string;
};

type InvoiceSettings = {
    tax_enabled: boolean;
    tax_label: string;
    default_tax_rate: string;
    levy_enabled: boolean;
    levy_label: string;
    levy_rate: string;
    number_prefix: string;
    number_padding: number;
    next_number: number;
    default_terms: string;
    payment: {
        instructions: string;
        bank_name: string;
        account_name: string;
        account_number: string;
        account_branch: string;
        swift_code: string;
    };
    template: InvoiceTemplate;
};

type Props = {
    settings: InvoiceSettings;
    logos: InvoiceLogo[];
    firm: {
        firm_name: string | null;
        tax_id: string | null;
        currency: string | null;
    } | null;
};

const layouts: Array<{
    value: InvoiceTemplate['layout'];
    title: string;
    description: string;
}> = [
    {
        value: 'classic',
        title: 'Classic',
        description: 'Formal hierarchy for legal and corporate billing.',
    },
    {
        value: 'modern',
        title: 'Modern',
        description: 'A clear accent band and confident contemporary layout.',
    },
    {
        value: 'minimal',
        title: 'Minimal',
        description: 'A compact, understated document for simple invoices.',
    },
];

export default function InvoiceSettingsPage({ settings, logos, firm }: Props) {
    const form = useForm<InvoiceSettings & { logo_file: File | null }>({
        ...settings,
        logo_file: null,
    });
    const fileInput = useRef<HTMLInputElement>(null);
    const [localLogoPreview, setLocalLogoPreview] = useState<string | null>(
        null,
    );
    const invoiceNumber = `${form.data.number_prefix}-${String(
        form.data.next_number,
    ).padStart(form.data.number_padding, '0')}`;
    const logoPreview =
        localLogoPreview ?? form.data.template.logo?.preview_url ?? null;

    useEffect(() => {
        return () => {
            if (localLogoPreview !== null) {
                URL.revokeObjectURL(localLogoPreview);
            }
        };
    }, [localLogoPreview]);

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.put(update().url, { preserveScroll: true, forceFormData: true });
    }

    function updateTemplate<K extends keyof InvoiceTemplate>(
        key: K,
        value: InvoiceTemplate[K],
    ): void {
        form.setData('template', { ...form.data.template, [key]: value });
    }

    function updatePayment<K extends keyof InvoiceSettings['payment']>(
        key: K,
        value: InvoiceSettings['payment'][K],
    ): void {
        form.setData('payment', { ...form.data.payment, [key]: value });
    }

    return (
        <>
            <Head title="Invoice settings" />
            <form
                onSubmit={submit}
                className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6"
            >
                <header className="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-start sm:justify-between">
                    <div className="grid gap-2">
                        <p className="text-sm font-medium text-primary">
                            Practice billing
                        </p>
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <Settings2 className="size-5" />
                            </div>
                            <div>
                                <h1 className="text-2xl font-semibold tracking-tight">
                                    Invoice settings
                                </h1>
                                <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                                    Set firm-wide tax, invoice numbering, and
                                    document presentation defaults.
                                </p>
                            </div>
                        </div>
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? <Spinner /> : <Save />}
                        Save settings
                    </Button>
                </header>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_25rem]">
                    <div className="grid gap-6">
                        <section className="rounded-xl border border-border/70 bg-background p-5">
                            <div className="flex gap-3">
                                <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <ReceiptText className="size-4" />
                                </div>
                                <div>
                                    <h2 className="font-semibold">Tax</h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Configure invoice-wide VAT and MLS Levy
                                        lines for new invoices.
                                    </p>
                                </div>
                            </div>
                            <div className="mt-5 grid gap-4 sm:grid-cols-[minmax(0,1fr)_10rem]">
                                <label className="grid gap-1.5 text-sm font-medium">
                                    VAT label
                                    <Input
                                        value={form.data.tax_label}
                                        disabled={!form.data.tax_enabled}
                                        placeholder="VAT"
                                        onChange={(event) =>
                                            form.setData(
                                                'tax_label',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <FieldError error={form.errors.tax_label} />
                                </label>
                                <label className="grid gap-1.5 text-sm font-medium">
                                    VAT rate (%)
                                    <Input
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        value={form.data.default_tax_rate}
                                        disabled={!form.data.tax_enabled}
                                        onChange={(event) =>
                                            form.setData(
                                                'default_tax_rate',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <FieldError
                                        error={form.errors.default_tax_rate}
                                    />
                                </label>
                            </div>
                            <label className="mt-5 flex cursor-pointer items-center gap-3 rounded-lg border border-border/70 bg-muted/25 px-3 py-3 text-sm">
                                <Checkbox
                                    checked={form.data.tax_enabled}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'tax_enabled',
                                            checked === true,
                                        )
                                    }
                                />
                                <span>
                                    <span className="block font-medium">
                                        Enable VAT on new invoices
                                    </span>
                                    <span className="text-muted-foreground">
                                        The configured rate is saved with each
                                        new invoice draft.
                                    </span>
                                </span>
                            </label>
                            <div className="mt-4 grid gap-4 sm:grid-cols-[minmax(0,1fr)_10rem]">
                                <label className="grid gap-1.5 text-sm font-medium">
                                    Levy label
                                    <Input
                                        value={form.data.levy_label}
                                        disabled={!form.data.levy_enabled}
                                        onChange={(event) =>
                                            form.setData(
                                                'levy_label',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <FieldError
                                        error={form.errors.levy_label}
                                    />
                                </label>
                                <label className="grid gap-1.5 text-sm font-medium">
                                    Levy rate (%)
                                    <Input
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        value={form.data.levy_rate}
                                        disabled={!form.data.levy_enabled}
                                        onChange={(event) =>
                                            form.setData(
                                                'levy_rate',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <FieldError error={form.errors.levy_rate} />
                                </label>
                            </div>
                            <label className="mt-4 flex cursor-pointer items-center gap-3 rounded-lg border border-border/70 bg-muted/25 px-3 py-3 text-sm">
                                <Checkbox
                                    checked={form.data.levy_enabled}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'levy_enabled',
                                            checked === true,
                                        )
                                    }
                                />
                                <span>
                                    <span className="block font-medium">
                                        Enable MLS levy on new invoices
                                    </span>
                                    <span className="text-muted-foreground">
                                        The configured rate is saved with each
                                        new invoice draft.
                                    </span>
                                </span>
                            </label>
                        </section>

                        <section className="rounded-xl border border-border/70 bg-background p-5">
                            <div className="flex gap-3">
                                <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <ReceiptText className="size-4" />
                                </div>
                                <div>
                                    <h2 className="font-semibold">
                                        Payment instructions
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        These details appear on printed
                                        invoices. Verify them before use.
                                    </p>
                                </div>
                            </div>
                            <div className="mt-5 grid gap-4 md:grid-cols-2">
                                <label className="grid gap-1.5 text-sm font-medium md:col-span-2">
                                    Payment instructions
                                    <textarea
                                        className="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                        value={form.data.payment.instructions}
                                        onChange={(event) =>
                                            updatePayment(
                                                'instructions',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <FieldError
                                        error={
                                            form.errors['payment.instructions']
                                        }
                                    />
                                </label>
                                {(
                                    [
                                        ['bank_name', 'Bank name'],
                                        ['account_name', 'Account name'],
                                        ['account_number', 'Account number'],
                                        ['account_branch', 'Account branch'],
                                        ['swift_code', 'SWIFT code'],
                                    ] as const
                                ).map(([key, label]) => (
                                    <label
                                        key={key}
                                        className="grid gap-1.5 text-sm font-medium"
                                    >
                                        {label}
                                        <Input
                                            value={form.data.payment[key]}
                                            onChange={(event) =>
                                                updatePayment(
                                                    key,
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <FieldError
                                            error={
                                                form.errors[`payment.${key}`]
                                            }
                                        />
                                    </label>
                                ))}
                            </div>
                        </section>

                        <section className="rounded-xl border border-border/70 bg-background p-5">
                            <div className="flex gap-3">
                                <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <FileText className="size-4" />
                                </div>
                                <div>
                                    <h2 className="font-semibold">
                                        Invoice numbering
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Numbers are reserved safely when a new
                                        invoice draft is saved.
                                    </p>
                                </div>
                            </div>
                            <div className="mt-5 grid gap-4 sm:grid-cols-3">
                                <label className="grid gap-1.5 text-sm font-medium">
                                    Prefix
                                    <Input
                                        value={form.data.number_prefix}
                                        placeholder="INV"
                                        onChange={(event) =>
                                            form.setData(
                                                'number_prefix',
                                                event.target.value.toUpperCase(),
                                            )
                                        }
                                    />
                                    <FieldError
                                        error={form.errors.number_prefix}
                                    />
                                </label>
                                <label className="grid gap-1.5 text-sm font-medium">
                                    Digits
                                    <Input
                                        type="number"
                                        min="3"
                                        max="10"
                                        value={form.data.number_padding}
                                        onChange={(event) =>
                                            form.setData(
                                                'number_padding',
                                                Number(event.target.value),
                                            )
                                        }
                                    />
                                    <FieldError
                                        error={form.errors.number_padding}
                                    />
                                </label>
                                <label className="grid gap-1.5 text-sm font-medium">
                                    Next number
                                    <Input
                                        type="number"
                                        min="1"
                                        value={form.data.next_number}
                                        onChange={(event) =>
                                            form.setData(
                                                'next_number',
                                                Number(event.target.value),
                                            )
                                        }
                                    />
                                    <FieldError
                                        error={form.errors.next_number}
                                    />
                                </label>
                            </div>
                        </section>

                        <section className="rounded-xl border border-border/70 bg-background p-5">
                            <div className="flex gap-3">
                                <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <Palette className="size-4" />
                                </div>
                                <div>
                                    <h2 className="font-semibold">
                                        Template builder
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Choose how future invoice documents are
                                        presented to clients.
                                    </p>
                                </div>
                            </div>
                            <div className="mt-5 grid gap-3 md:grid-cols-3">
                                {layouts.map((layout) => {
                                    const selected =
                                        form.data.template.layout ===
                                        layout.value;

                                    return (
                                        <button
                                            key={layout.value}
                                            type="button"
                                            className={`relative rounded-xl border p-4 text-left transition-colors ${
                                                selected
                                                    ? 'border-primary bg-primary/5 shadow-sm'
                                                    : 'border-border/70 hover:border-primary/40 hover:bg-muted/30'
                                            }`}
                                            onClick={() =>
                                                updateTemplate(
                                                    'layout',
                                                    layout.value,
                                                )
                                            }
                                        >
                                            {selected && (
                                                <span className="absolute top-3 right-3 flex size-5 items-center justify-center rounded-full bg-primary text-primary-foreground">
                                                    <Check className="size-3" />
                                                </span>
                                            )}
                                            <p className="font-medium">
                                                {layout.title}
                                            </p>
                                            <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                                {layout.description}
                                            </p>
                                        </button>
                                    );
                                })}
                            </div>
                            <div className="mt-5 grid gap-4 md:grid-cols-2">
                                <label className="grid gap-1.5 text-sm font-medium">
                                    Accent colour
                                    <div className="flex gap-2">
                                        <input
                                            type="color"
                                            value={
                                                form.data.template.accent_color
                                            }
                                            className="h-9 w-11 cursor-pointer rounded-md border border-input bg-background p-1"
                                            onChange={(event) =>
                                                updateTemplate(
                                                    'accent_color',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <Input
                                            value={
                                                form.data.template.accent_color
                                            }
                                            onChange={(event) =>
                                                updateTemplate(
                                                    'accent_color',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <FieldError
                                        error={
                                            form.errors['template.accent_color']
                                        }
                                    />
                                </label>
                                <label className="grid gap-1.5 text-sm font-medium">
                                    Default payment terms
                                    <Input
                                        value={form.data.default_terms}
                                        placeholder="Payment is due within 30 days."
                                        onChange={(event) =>
                                            form.setData(
                                                'default_terms',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <FieldError
                                        error={form.errors.default_terms}
                                    />
                                </label>
                            </div>
                            <div className="mt-4 grid gap-4 md:grid-cols-2">
                                <label className="grid gap-1.5 text-sm font-medium">
                                    Header note
                                    <textarea
                                        className="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        value={form.data.template.header_note}
                                        onChange={(event) =>
                                            updateTemplate(
                                                'header_note',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </label>
                                <label className="grid gap-1.5 text-sm font-medium">
                                    Footer note
                                    <textarea
                                        className="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        value={form.data.template.footer_note}
                                        onChange={(event) =>
                                            updateTemplate(
                                                'footer_note',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </label>
                            </div>
                            <div className="mt-5 border-t pt-5">
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h3 className="text-sm font-semibold">
                                            Invoice logo
                                        </h3>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Select an approved image or upload a
                                            new logo to the company’s Cloudflare
                                            R2 storage.
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            fileInput.current?.click()
                                        }
                                    >
                                        <Upload /> Upload logo
                                    </Button>
                                    <input
                                        ref={fileInput}
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        className="hidden"
                                        onChange={(event) => {
                                            const [file] = Array.from(
                                                event.target.files ?? [],
                                            );

                                            if (file === undefined) {
                                                return;
                                            }

                                            form.setData('logo_file', file);
                                            setLocalLogoPreview(
                                                URL.createObjectURL(file),
                                            );
                                        }}
                                    />
                                </div>
                                {form.data.logo_file !== null && (
                                    <div className="mt-4 flex items-center justify-between gap-3 rounded-lg border border-primary/25 bg-primary/5 px-3 py-2 text-sm">
                                        <span className="min-w-0 truncate">
                                            {form.data.logo_file.name} is ready
                                            to upload when you save settings.
                                        </span>
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            aria-label="Remove uploaded logo"
                                            onClick={() => {
                                                form.setData('logo_file', null);
                                                setLocalLogoPreview(null);

                                                if (
                                                    fileInput.current !== null
                                                ) {
                                                    fileInput.current.value =
                                                        '';
                                                }
                                            }}
                                        >
                                            <X className="size-4" />
                                        </Button>
                                    </div>
                                )}
                                <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                    {logos.map((logo) => {
                                        const selected =
                                            form.data.logo_file === null &&
                                            form.data.template.logo?.id ===
                                                logo.id;

                                        return (
                                            <button
                                                key={logo.id}
                                                type="button"
                                                className={`relative flex min-h-30 flex-col rounded-lg border p-3 text-left transition-colors ${
                                                    selected
                                                        ? 'border-primary bg-primary/5 shadow-sm'
                                                        : 'border-border/70 hover:border-primary/40 hover:bg-muted/30'
                                                }`}
                                                onClick={() => {
                                                    form.setData('template', {
                                                        ...form.data.template,
                                                        logo,
                                                    });
                                                    form.setData(
                                                        'logo_file',
                                                        null,
                                                    );
                                                    setLocalLogoPreview(null);
                                                }}
                                            >
                                                {selected && (
                                                    <span className="absolute top-2 right-2 flex size-5 items-center justify-center rounded-full bg-primary text-primary-foreground">
                                                        <Check className="size-3" />
                                                    </span>
                                                )}
                                                <div className="flex min-h-12 items-center rounded-md border bg-background p-2">
                                                    <img
                                                        src={logo.preview_url}
                                                        alt=""
                                                        className="max-h-10 max-w-full object-contain"
                                                    />
                                                </div>
                                                <span className="mt-2 truncate text-sm font-medium">
                                                    {logo.name}
                                                </span>
                                                <span className="mt-0.5 text-xs text-muted-foreground">
                                                    {logo.source}
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                                {form.data.template.logo !== null &&
                                    form.data.logo_file === null && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="mt-3"
                                            onClick={() =>
                                                updateTemplate('logo', null)
                                            }
                                        >
                                            <X /> Remove selected logo
                                        </Button>
                                    )}
                            </div>
                            <div className="mt-4 grid gap-3 md:grid-cols-2">
                                <ToggleRow
                                    checked={form.data.template.show_tax_id}
                                    label="Show firm tax ID"
                                    description="Display the firm registration/tax identifier."
                                    onCheckedChange={(checked) =>
                                        updateTemplate('show_tax_id', checked)
                                    }
                                />
                                <ToggleRow
                                    checked={
                                        form.data.template.show_matter_reference
                                    }
                                    label="Show matter reference"
                                    description="Include the linked matter number where available."
                                    onCheckedChange={(checked) =>
                                        updateTemplate(
                                            'show_matter_reference',
                                            checked,
                                        )
                                    }
                                />
                            </div>
                        </section>
                    </div>

                    <aside className="h-fit rounded-xl border border-border/70 bg-muted/20 p-5 xl:sticky xl:top-4">
                        <p className="text-sm font-medium text-muted-foreground">
                            Live document preview
                        </p>
                        <div className="mt-4 overflow-hidden rounded-lg border bg-background shadow-sm">
                            <div
                                className={`p-5 ${
                                    form.data.template.layout === 'modern'
                                        ? 'border-t-8'
                                        : ''
                                }`}
                                style={{
                                    borderColor:
                                        form.data.template.accent_color,
                                }}
                            >
                                <div className="flex items-start justify-between gap-4">
                                    <div className="min-w-0">
                                        {logoPreview === null ? (
                                            <p
                                                className="text-lg font-bold tracking-tight"
                                                style={{
                                                    color: form.data.template
                                                        .accent_color,
                                                }}
                                            >
                                                {firm?.firm_name ??
                                                    'Zolomphi & Co.'}
                                            </p>
                                        ) : (
                                            <img
                                                src={logoPreview}
                                                alt={
                                                    form.data.template.logo
                                                        ?.name ?? 'Invoice logo'
                                                }
                                                className="h-11 max-w-48 object-contain object-left"
                                            />
                                        )}
                                        <p className="mt-1 text-[10px] text-muted-foreground">
                                            {form.data.template.header_note}
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                            Invoice
                                        </p>
                                        <p className="mt-1 font-mono text-xs font-semibold">
                                            {invoiceNumber}
                                        </p>
                                    </div>
                                </div>
                                <div className="mt-7 grid gap-1 border-y py-3 text-[10px] text-muted-foreground">
                                    <p className="font-medium text-foreground">
                                        Client name
                                    </p>
                                    {form.data.template
                                        .show_matter_reference && (
                                        <p>Matter: MAT-000001</p>
                                    )}
                                    {form.data.template.show_tax_id &&
                                        firm?.tax_id && (
                                            <p>Tax ID: {firm.tax_id}</p>
                                        )}
                                </div>
                                <div className="mt-4 grid gap-2 text-[10px]">
                                    <div className="flex justify-between font-medium text-muted-foreground">
                                        <span>Professional services</span>
                                        <span>
                                            {firm?.currency ?? 'MWK'} 100.00
                                        </span>
                                    </div>
                                    {form.data.tax_enabled && (
                                        <div className="flex justify-between text-muted-foreground">
                                            <span>
                                                {form.data.tax_label} (
                                                {form.data.default_tax_rate}%)
                                            </span>
                                            <span>
                                                {firm?.currency ?? 'MWK'}{' '}
                                                {(
                                                    (100 *
                                                        Number(
                                                            form.data
                                                                .default_tax_rate,
                                                        )) /
                                                    100
                                                ).toFixed(2)}
                                            </span>
                                        </div>
                                    )}
                                    {form.data.levy_enabled && (
                                        <div className="flex justify-between text-muted-foreground">
                                            <span>
                                                {form.data.levy_label} (
                                                {form.data.levy_rate}%)
                                            </span>
                                            <span>
                                                {firm?.currency ?? 'MWK'}{' '}
                                                {(
                                                    (100 *
                                                        Number(
                                                            form.data.levy_rate,
                                                        )) /
                                                    100
                                                ).toFixed(2)}
                                            </span>
                                        </div>
                                    )}
                                    <div
                                        className="mt-1 flex justify-between border-t pt-2 text-xs font-semibold"
                                        style={{
                                            color: form.data.template
                                                .accent_color,
                                        }}
                                    >
                                        <span>Total due</span>
                                        <span>
                                            {firm?.currency ?? 'MWK'}{' '}
                                            {(
                                                100 +
                                                (form.data.tax_enabled
                                                    ? Number(
                                                          form.data
                                                              .default_tax_rate,
                                                      )
                                                    : 0) +
                                                (form.data.levy_enabled
                                                    ? Number(
                                                          form.data.levy_rate,
                                                      )
                                                    : 0)
                                            ).toFixed(2)}
                                        </span>
                                    </div>
                                </div>
                                <p className="mt-7 text-[10px] leading-4 text-muted-foreground">
                                    {form.data.template.footer_note}
                                </p>
                            </div>
                        </div>
                        <p className="mt-4 text-xs leading-5 text-muted-foreground">
                            New drafts use these tax, terms, and numbering
                            defaults. Existing drafts remain unchanged.
                        </p>
                    </aside>
                </div>
            </form>
        </>
    );
}

function ToggleRow({
    checked,
    label,
    description,
    onCheckedChange,
}: {
    checked: boolean;
    label: string;
    description: string;
    onCheckedChange: (checked: boolean) => void;
}) {
    return (
        <label className="flex cursor-pointer items-center gap-3 rounded-lg border border-border/70 px-3 py-3 text-sm">
            <Checkbox
                checked={checked}
                onCheckedChange={(value) => onCheckedChange(value === true)}
            />
            <span>
                <span className="block font-medium">{label}</span>
                <span className="text-xs text-muted-foreground">
                    {description}
                </span>
            </span>
        </label>
    );
}

function FieldError({ error }: { error?: string }) {
    return error ? (
        <span className="text-xs font-normal text-destructive">{error}</span>
    ) : null;
}

InvoiceSettingsPage.layout = {
    breadcrumbs: [
        { title: 'Invoices', href: invoices() },
        { title: 'Invoice settings', href: edit() },
    ],
};
