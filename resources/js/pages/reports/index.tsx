import { Form, Head } from '@inertiajs/react';
import { BarChart3 } from 'lucide-react';
import { ComparisonChart } from '@/components/monitoring/charts';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCurrency } from '@/hooks/use-currency';
import { index as reports } from '@/routes/reports';

type Props = {
    filters: { start_date: string; end_date: string };
    summary: {
        matters_opened: number;
        invoices_issued: number;
        amount_billed: string;
        payments_collected: string;
        outstanding_balance: string;
        overdue_tasks: number;
    };
    matters_by_status: Array<{ label: string; value: number }>;
    billing_by_status: Array<{ label: string; value: number; amount: string }>;
    tasks_by_status: Array<{ label: string; value: number }>;
    activity_trend: Array<{
        label: string;
        matters: number;
        tasks_completed: number;
        invoices: number;
        payments: number;
    }>;
};
export default function Reports({
    filters,
    summary,
    matters_by_status,
    billing_by_status,
    tasks_by_status,
    activity_trend,
}: Props) {
    const { formatMoney } = useCurrency();
    const metrics = [
        { label: 'Matters opened', value: summary.matters_opened },
        { label: 'Invoices issued', value: summary.invoices_issued },
        { label: 'Amount billed', value: formatMoney(summary.amount_billed) },
        {
            label: 'Payments collected',
            value: formatMoney(summary.payments_collected),
        },
        {
            label: 'Outstanding now',
            value: formatMoney(summary.outstanding_balance),
        },
        { label: 'Overdue tasks', value: summary.overdue_tasks },
    ];

    return (
        <>
            <Head title="Reports" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
                <header>
                    <p className="text-sm font-medium text-primary">
                        Practice intelligence
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Reports
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Review legal-work and billing performance for any
                        reporting period.
                    </p>
                </header>
                <Form
                    action={reports().url}
                    method="get"
                    className="flex flex-col gap-3 rounded-xl bg-muted/40 p-3 sm:flex-row sm:items-end"
                >
                    <label className="grid gap-1 text-sm font-medium">
                        Start date
                        <Input
                            name="start_date"
                            type="date"
                            defaultValue={filters.start_date}
                        />
                    </label>
                    <label className="grid gap-1 text-sm font-medium">
                        End date
                        <Input
                            name="end_date"
                            type="date"
                            defaultValue={filters.end_date}
                        />
                    </label>
                    <Button type="submit">Apply range</Button>
                </Form>
                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {metrics.map((metric) => (
                        <div
                            key={metric.label}
                            className="rounded-xl border bg-background p-4"
                        >
                            <p className="text-sm text-muted-foreground">
                                {metric.label}
                            </p>
                            <p className="mt-2 text-2xl font-semibold">
                                {metric.value}
                            </p>
                        </div>
                    ))}
                </section>
                <section className="grid gap-6 xl:grid-cols-2">
                    <ComparisonChart
                        title="Matter delivery trend"
                        firstLabel="Matters opened"
                        secondLabel="Tasks completed"
                        points={activity_trend.map((point) => ({
                            label: point.label,
                            first: point.matters,
                            second: point.tasks_completed,
                        }))}
                    />
                    <ComparisonChart
                        title="Billing activity trend"
                        firstLabel="Invoices issued"
                        secondLabel="Payments received"
                        points={activity_trend.map((point) => ({
                            label: point.label,
                            first: point.invoices,
                            second: point.payments,
                        }))}
                    />
                </section>
                <section className="grid gap-6 xl:grid-cols-3">
                    <Breakdown
                        title="Matters by status"
                        entries={matters_by_status}
                    />
                    <Breakdown
                        title="Billing by status"
                        entries={billing_by_status.map((entry) => ({
                            ...entry,
                            detail: formatMoney(entry.amount),
                        }))}
                    />
                    <Breakdown
                        title="Tasks by status"
                        entries={tasks_by_status}
                    />
                </section>
            </div>
        </>
    );
}
function Breakdown({
    title,
    entries,
}: {
    title: string;
    entries: Array<{ label: string; value: number; detail?: string }>;
}) {
    const total = Math.max(
        1,
        entries.reduce((sum, entry) => sum + entry.value, 0),
    );

    return (
        <section className="rounded-xl border bg-background p-5">
            <div className="mb-5 flex items-center gap-2">
                <BarChart3 className="size-5 text-primary" />
                <h2 className="font-semibold">{title}</h2>
            </div>
            <div className="space-y-4">
                {entries.map((entry) => (
                    <div key={entry.label}>
                        <div className="mb-1 flex justify-between gap-3 text-sm">
                            <span className="capitalize">
                                {entry.label.replaceAll('_', ' ')}
                            </span>
                            <span className="font-medium">
                                {entry.value}
                                {entry.detail ? ` · ${entry.detail}` : ''}
                            </span>
                        </div>
                        <div className="h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full rounded-full bg-primary"
                                style={{
                                    width: `${(entry.value / total) * 100}%`,
                                }}
                            />
                        </div>
                    </div>
                ))}
                {entries.length === 0 && (
                    <p className="py-8 text-center text-sm text-muted-foreground">
                        No records in this reporting period.
                    </p>
                )}
            </div>
        </section>
    );
}
Reports.layout = { breadcrumbs: [{ title: 'Reports', href: reports() }] };
