import { Link } from '@inertiajs/react';
import { Plus, Printer, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCurrency } from '@/hooks/use-currency';
import { create, edit, print as printInvoice } from '@/routes/invoices';
import { index as payments } from '@/routes/payments';

type Invoice = {
    id: number;
    invoice_number: string;
    client: string;
    matter: { matter_number: string; title: string } | null;
    status: string;
    currency: string;
    issued_at: string | null;
    due_at: string | null;
    total_amount: string;
    paid_amount: string;
    balance_due: string;
    created_by: string | null;
};

const pageSize = 10;

export default function InvoiceManagement({
    invoices,
}: {
    invoices: Invoice[];
}) {
    const { formatMoney } = useCurrency();
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('all');
    const [page, setPage] = useState(1);
    const filteredInvoices = useMemo(() => {
        const term = search.trim().toLocaleLowerCase();

        return invoices.filter((invoice) => {
            const matchesSearch =
                term === '' ||
                [
                    invoice.invoice_number,
                    invoice.client,
                    invoice.matter?.matter_number,
                    invoice.matter?.title,
                ].some((value) => value?.toLocaleLowerCase().includes(term));

            return (
                matchesSearch && (status === 'all' || invoice.status === status)
            );
        });
    }, [invoices, search, status]);
    const totalPages = Math.max(
        1,
        Math.ceil(filteredInvoices.length / pageSize),
    );
    const currentPage = Math.min(page, totalPages);
    const visibleInvoices = filteredInvoices.slice(
        (currentPage - 1) * pageSize,
        currentPage * pageSize,
    );

    return (
        <section className="grid gap-5">
            <header className="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-start sm:justify-between">
                <p className="text-sm text-muted-foreground">
                    {filteredInvoices.length} of {invoices.length} invoice
                    {invoices.length === 1 ? '' : 's'} in the practice
                    workspace.
                </p>
                <Button asChild>
                    <Link href={create()}>
                        <Plus /> Build invoice
                    </Link>
                </Button>
            </header>

            <div className="grid gap-3 rounded-xl bg-muted/35 p-3 md:grid-cols-[minmax(0,1fr)_10rem]">
                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        aria-label="Search invoices"
                        className="bg-background pl-9"
                        value={search}
                        placeholder="Search invoice, client, or matter"
                        onChange={(event) => {
                            setSearch(event.target.value);
                            setPage(1);
                        }}
                    />
                </div>
                <select
                    aria-label="Filter invoices by status"
                    className="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs"
                    value={status}
                    onChange={(event) => {
                        setStatus(event.target.value);
                        setPage(1);
                    }}
                >
                    <option value="all">All statuses</option>
                    <option value="draft">Draft</option>
                    <option value="issued">Issued</option>
                    <option value="partially_paid">Partially paid</option>
                    <option value="paid">Paid</option>
                </select>
            </div>

            <div className="overflow-x-auto rounded-xl border border-border/70 bg-background">
                <table className="w-full min-w-250 text-left text-sm">
                    <thead className="bg-muted/50 text-xs tracking-wide text-muted-foreground uppercase">
                        <tr>
                            <th className="px-4 py-3 font-medium">Invoice</th>
                            <th className="px-4 py-3 font-medium">
                                Client / matter
                            </th>
                            <th className="px-4 py-3 font-medium">Dates</th>
                            <th className="px-4 py-3 font-medium">Total</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {visibleInvoices.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={6}
                                    className="px-4 py-12 text-center text-muted-foreground"
                                >
                                    No invoices match the current filters.
                                </td>
                            </tr>
                        ) : (
                            visibleInvoices.map((invoice) => (
                                <tr
                                    key={invoice.id}
                                    className="transition-colors hover:bg-muted/30"
                                >
                                    <td className="px-4 py-3 align-top">
                                        <p className="font-mono text-xs font-medium">
                                            {invoice.invoice_number}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {invoice.created_by ?? 'System'}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3 align-top">
                                        <p className="font-medium">
                                            {invoice.client}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {invoice.matter === null
                                                ? 'Client-level invoice'
                                                : `${invoice.matter.matter_number} · ${invoice.matter.title}`}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3 align-top text-muted-foreground">
                                        <p>Issued {invoice.issued_at ?? '—'}</p>
                                        <p className="mt-1 text-xs">
                                            Due {invoice.due_at ?? '—'}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3 align-top font-medium">
                                        {formatMoney(
                                            invoice.total_amount,
                                            invoice.currency,
                                        )}
                                        <p className="mt-1 text-xs font-normal text-muted-foreground">
                                            Paid{' '}
                                            {formatMoney(
                                                invoice.paid_amount,
                                                invoice.currency,
                                            )}
                                        </p>
                                        <p className="mt-1 text-xs font-normal text-muted-foreground">
                                            Balance{' '}
                                            {formatMoney(
                                                invoice.balance_due,
                                                invoice.currency,
                                            )}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3 align-top">
                                        <Badge className="border-amber-200 bg-amber-100 text-amber-800 dark:border-amber-900/70 dark:bg-amber-950/50 dark:text-amber-300">
                                            {invoice.status}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-right align-top">
                                        <div className="flex flex-wrap justify-end gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                asChild
                                            >
                                                <Link
                                                    href={printInvoice(
                                                        invoice.id,
                                                    )}
                                                >
                                                    <Printer /> Print
                                                </Link>
                                            </Button>
                                            {invoice.status === 'draft' ? (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <Link
                                                        href={edit(invoice.id)}
                                                    >
                                                        Edit draft
                                                    </Link>
                                                </Button>
                                            ) : invoice.balance_due !==
                                              '0.00' ? (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <Link href={payments()}>
                                                        Record payment
                                                    </Link>
                                                </Button>
                                            ) : null}
                                        </div>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            <div className="flex flex-col gap-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                <p>
                    {filteredInvoices.length === 0
                        ? 'No invoices found'
                        : `Showing ${(currentPage - 1) * pageSize + 1}–${Math.min(currentPage * pageSize, filteredInvoices.length)} of ${filteredInvoices.length} invoices`}
                </p>
                <div className="flex items-center gap-2">
                    <Button
                        size="sm"
                        variant="outline"
                        disabled={currentPage === 1}
                        onClick={() => setPage((value) => value - 1)}
                    >
                        Previous
                    </Button>
                    <span>
                        Page {currentPage} of {totalPages}
                    </span>
                    <Button
                        size="sm"
                        variant="outline"
                        disabled={currentPage === totalPages}
                        onClick={() => setPage((value) => value + 1)}
                    >
                        Next
                    </Button>
                </div>
            </div>
        </section>
    );
}
