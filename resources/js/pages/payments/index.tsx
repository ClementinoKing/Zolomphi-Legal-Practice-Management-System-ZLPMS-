import { Form, Head } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useCurrency } from '@/hooks/use-currency';
import { index, store } from '@/routes/payments';
import { store as storePlan } from '@/routes/payments/plans';

type Invoice = {
    id: number;
    number: string;
    client: string;
    balance: string;
    currency: string;
};
type Payment = {
    id: number;
    number: string;
    invoice: string;
    client: string;
    amount: string;
    currency: string;
    method: string;
    received_at: string;
    reference: string | null;
    recorder: string | null;
};
type Plan = {
    id: number;
    invoice: string;
    client: string;
    status: string;
    amount: string;
    currency: string;
    installments: Array<{
        number: number;
        amount: string;
        paid: string;
        due_at: string;
        status: string;
    }>;
};
const pageSize = 10;
export default function Payments({
    payments: records,
    invoices,
    paymentMethods,
    plans: paymentPlans,
}: {
    payments: Payment[];
    invoices: Invoice[];
    paymentMethods: Array<{ id: number; name: string }>;
    plans: Plan[];
}) {
    const { formatMoney } = useCurrency();
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const filtered = useMemo(
        () =>
            records.filter((p) =>
                `${p.number} ${p.invoice} ${p.client} ${p.reference ?? ''}`
                    .toLowerCase()
                    .includes(search.toLowerCase()),
            ),
        [records, search],
    );
    const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
    const currentPage = Math.min(page, totalPages);
    const visible = filtered.slice(
        (currentPage - 1) * pageSize,
        currentPage * pageSize,
    );

    return (
        <>
            <Head title="Payments" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Practice billing
                        </p>
                        <h1 className="text-2xl font-semibold">Payments</h1>
                        <p className="text-sm text-muted-foreground">
                            Record invoice payments and manage staged repayment
                            plans.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <PlanDialog invoices={invoices} />
                        <PaymentDialog
                            invoices={invoices}
                            methods={paymentMethods}
                        />
                    </div>
                </header>
                <div className="relative">
                    <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        className="pl-9"
                        value={search}
                        onChange={(e) => {
                            setSearch(e.target.value);
                            setPage(1);
                        }}
                        placeholder="Search payment, invoice, client, or reference"
                    />
                </div>
                <section className="overflow-x-auto rounded-xl border bg-background">
                    <table className="w-full min-w-225 text-sm">
                        <thead className="bg-muted/50 text-left text-xs text-muted-foreground uppercase">
                            <tr>
                                <th className="p-4">Payment</th>
                                <th className="p-4">Invoice / client</th>
                                <th className="p-4">Method</th>
                                <th className="p-4">Amount</th>
                                <th className="p-4">Received</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {visible.map((p) => (
                                <tr
                                    key={p.id}
                                    className="transition-colors hover:bg-muted/30"
                                >
                                    <td className="p-4 font-mono text-xs">
                                        {p.number}
                                        <p className="mt-1 font-sans text-muted-foreground">
                                            {p.reference ?? 'No reference'}
                                        </p>
                                    </td>
                                    <td className="p-4">
                                        <b>{p.invoice}</b>
                                        <p className="text-muted-foreground">
                                            {p.client}
                                        </p>
                                    </td>
                                    <td className="p-4">{p.method}</td>
                                    <td className="p-4 font-medium">
                                        {formatMoney(p.amount, p.currency)}
                                    </td>
                                    <td className="p-4">{p.received_at}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {visible.length === 0 && (
                        <p className="p-12 text-center text-muted-foreground">
                            No payments recorded yet.
                        </p>
                    )}
                </section>
                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <p>
                        {filtered.length === 0
                            ? 'No payments found'
                            : `Showing ${(currentPage - 1) * pageSize + 1}–${Math.min(currentPage * pageSize, filtered.length)} of ${filtered.length} payments`}
                    </p>
                    <div className="flex gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={currentPage === 1}
                            onClick={() => setPage((value) => value - 1)}
                        >
                            Previous
                        </Button>
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
                <section className="grid gap-3">
                    <h2 className="text-lg font-semibold">Payment plans</h2>
                    {paymentPlans.map((plan) => (
                        <div
                            key={plan.id}
                            className="rounded-xl border bg-background p-4"
                        >
                            <div className="flex justify-between">
                                <div>
                                    <b>{plan.invoice}</b>
                                    <p className="text-sm text-muted-foreground">
                                        {plan.client} · {plan.status}
                                    </p>
                                </div>
                                <b>{formatMoney(plan.amount, plan.currency)}</b>
                            </div>
                            <div className="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                                {plan.installments.map((i) => (
                                    <div
                                        key={i.number}
                                        className="rounded-lg bg-muted/50 p-2 text-sm"
                                    >
                                        #{i.number} · {i.due_at}
                                        <br />
                                        {formatMoney(
                                            i.paid,
                                            plan.currency,
                                        )} /{' '}
                                        {formatMoney(i.amount, plan.currency)}
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                    {paymentPlans.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No staged payment plans.
                        </p>
                    )}
                </section>
            </div>
        </>
    );
}
function PaymentDialog({
    invoices,
    methods,
}: {
    invoices: Invoice[];
    methods: Array<{ id: number; name: string }>;
}) {
    const [isOpen, setIsOpen] = useState(false);

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus />
                    Record payment
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Record payment</DialogTitle>
                    <DialogDescription>
                        Apply a payment to an outstanding invoice.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={store().url}
                    method="post"
                    resetOnSuccess
                    className="grid gap-3"
                    onSuccess={() => setIsOpen(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <select
                                name="invoice_id"
                                className="h-10 rounded-md border px-3"
                                required
                            >
                                <option value="">Invoice</option>
                                {invoices.map((i) => (
                                    <option value={i.id} key={i.id}>
                                        {i.number} · {i.client} ({i.balance}{' '}
                                        {i.currency})
                                    </option>
                                ))}
                            </select>
                            <select
                                name="payment_method_id"
                                className="h-10 rounded-md border px-3"
                                required
                            >
                                <option value="">Payment method</option>
                                {methods.map((m) => (
                                    <option value={m.id} key={m.id}>
                                        {m.name}
                                    </option>
                                ))}
                            </select>
                            <Input
                                name="amount"
                                type="number"
                                step="0.01"
                                placeholder="Amount"
                                required
                            />
                            <Input
                                name="received_at"
                                type="date"
                                defaultValue={new Date()
                                    .toISOString()
                                    .slice(0, 10)}
                                required
                            />
                            <Input
                                name="reference"
                                placeholder="Reference (optional)"
                            />
                            <textarea
                                name="notes"
                                className="min-h-20 rounded-md border p-3"
                                placeholder="Notes (optional)"
                            />
                            {errors.amount && (
                                <p className="text-sm text-destructive">
                                    {errors.amount}
                                </p>
                            )}
                            <Button disabled={processing}>Save payment</Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function PlanDialog({ invoices }: { invoices: Invoice[] }) {
    const [isOpen, setIsOpen] = useState(false);

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">Create payment plan</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create staged plan</DialogTitle>
                    <DialogDescription>
                        Split the outstanding balance into equal monthly
                        installments.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={storePlan().url}
                    method="post"
                    resetOnSuccess
                    className="grid gap-3"
                    onSuccess={() => setIsOpen(false)}
                >
                    {({ processing }) => (
                        <>
                            <select
                                name="invoice_id"
                                className="h-10 rounded-md border px-3"
                                required
                            >
                                <option value="">Invoice</option>
                                {invoices.map((i) => (
                                    <option value={i.id} key={i.id}>
                                        {i.number} · {i.client}
                                    </option>
                                ))}
                            </select>
                            <Input
                                name="installment_count"
                                type="number"
                                min="2"
                                max="60"
                                defaultValue="3"
                                required
                            />
                            <Input name="first_due_at" type="date" required />
                            <Button disabled={processing}>
                                Create monthly installments
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
Payments.layout = { breadcrumbs: [{ title: 'Payments', href: index() }] };
