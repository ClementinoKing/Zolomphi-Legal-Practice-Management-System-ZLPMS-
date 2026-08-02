import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    CalendarDays,
    CheckSquare,
    CircleDollarSign,
    Scale,
} from 'lucide-react';
import {
    ComparisonChart,
    DistributionChart,
} from '@/components/monitoring/charts';
import { useCurrency } from '@/hooks/use-currency';
import { dashboard } from '@/routes';
import { index as auditLogs } from '@/routes/audit-logs';
import { index as calendar } from '@/routes/calendar';
import { show as matterShow } from '@/routes/matters';
import { index as payments } from '@/routes/payments';
import type { RouteDefinition } from '@/wayfinder';

type DashboardProps = {
    sections: {
        matters: boolean;
        tasks: boolean;
        calendar: boolean;
        billing: boolean;
        invoices: boolean;
        payments: boolean;
        activity: boolean;
    };
    metrics: {
        open_matters: number | null;
        overdue_deadlines: number | null;
        unpaid_invoices: number | null;
        outstanding_balance: string | null;
        open_tasks: number | null;
        overdue_tasks: number | null;
    };
    deadlines: Array<{
        id: number;
        matter_number: string;
        title: string;
        client: string | null;
        deadline_at: string;
        is_overdue: boolean;
    }>;
    tasks: Array<{
        id: number;
        title: string;
        priority: string;
        status: string;
        due_at: string;
        matter_id: number | null;
        matter: string | null;
        client: string | null;
        is_overdue: boolean;
    }>;
    events: Array<{
        id: number;
        title: string;
        starts_at: string;
        is_all_day: boolean;
        matter_id: number | null;
        matter: string | null;
    }>;
    payments: Array<{
        id: number;
        payment_number: string;
        invoice_number: string;
        client: string | null;
        amount: string;
        currency: string;
        received_at: string;
    }>;
    activity: Array<{
        id: number;
        event: string;
        description: string | null;
        actor: string;
        matter_id: number | null;
        matter: string | null;
        client: string | null;
        created_at: string;
    }>;
    charts: {
        matter_activity: Array<{
            label: string;
            matters: number;
            tasks_completed: number;
            invoices: number;
            payments: number;
        }>;
        matter_distribution: Array<{ label: string; value: number }>;
    };
};

const dateFormatter = new Intl.DateTimeFormat('en-MW', {
    day: 'numeric',
    month: 'short',
});

export default function Dashboard({
    sections,
    metrics,
    deadlines,
    tasks,
    events,
    payments: recentPayments,
    activity,
    charts,
}: DashboardProps) {
    const { formatMoney } = useCurrency();
    const stats = [
        sections.matters && metrics.open_matters !== null
            ? {
                  label: 'Open matters',
                  value: metrics.open_matters,
                  detail: `${metrics.overdue_deadlines ?? 0} overdue deadline${metrics.overdue_deadlines === 1 ? '' : 's'}`,
                  icon: Scale,
                  tone: 'text-primary',
              }
            : null,
        sections.invoices && metrics.unpaid_invoices !== null
            ? {
                  label: 'Unpaid invoices',
                  value: metrics.unpaid_invoices,
                  detail: formatMoney(metrics.outstanding_balance ?? '0'),
                  icon: CircleDollarSign,
                  tone: 'text-orange-600',
              }
            : null,
        sections.tasks && metrics.open_tasks !== null
            ? {
                  label: 'Open tasks',
                  value: metrics.open_tasks,
                  detail: `${metrics.overdue_tasks ?? 0} overdue`,
                  icon: CheckSquare,
                  tone: 'text-sky-700',
              }
            : null,
    ].filter((stat): stat is NonNullable<typeof stat> => stat !== null);

    return (
        <>
            <Head title="Dashboard" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
                <header>
                    <p className="text-sm font-medium text-primary">
                        Practice overview
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Your workspace overview
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        A focused view of the work, deadlines, and information
                        assigned to your role.
                    </p>
                </header>
                <section
                    className={`grid gap-4 ${stats.length === 1 ? 'md:grid-cols-1' : stats.length === 2 ? 'md:grid-cols-2' : 'md:grid-cols-3'}`}
                >
                    {stats.map((stat) => (
                        <div
                            key={stat.label}
                            className="rounded-xl border bg-background p-5"
                        >
                            <div className="flex items-start justify-between">
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        {stat.label}
                                    </p>
                                    <p className="mt-2 text-3xl font-semibold">
                                        {stat.value}
                                    </p>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        {stat.detail}
                                    </p>
                                </div>
                                <stat.icon className={`size-5 ${stat.tone}`} />
                            </div>
                        </div>
                    ))}
                </section>
                {sections.matters && (
                    <section className="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
                        {sections.tasks && (
                            <ComparisonChart
                                title="Matter intake and task completion"
                                firstLabel="Matters opened"
                                secondLabel="Tasks completed"
                                points={charts.matter_activity.map((point) => ({
                                    label: point.label,
                                    first: point.matters,
                                    second: point.tasks_completed,
                                }))}
                            />
                        )}
                        <DistributionChart
                            title="Matter portfolio"
                            entries={charts.matter_distribution}
                        />
                    </section>
                )}
                {sections.billing && (
                    <ComparisonChart
                        title="Billing workflow"
                        firstLabel="Invoices issued"
                        secondLabel="Payments received"
                        points={charts.matter_activity.map((point) => ({
                            label: point.label,
                            first: point.invoices,
                            second: point.payments,
                        }))}
                    />
                )}
                {(sections.matters || sections.tasks || sections.calendar) && (
                    <section className="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
                        {(sections.matters || sections.tasks) && (
                            <Panel
                                title={
                                    sections.matters && sections.tasks
                                        ? 'Deadlines and tasks'
                                        : sections.tasks
                                          ? 'Assigned tasks'
                                          : 'Upcoming deadlines'
                                }
                                action={
                                    sections.calendar
                                        ? { title: 'Open calendar', href: calendar() }
                                        : undefined
                                }
                            >
                        <div className="divide-y">
                            {[
                                ...deadlines.map((item) => ({
                                    ...item,
                                    kind: 'deadline' as const,
                                })),
                                ...tasks.map((item) => ({
                                    ...item,
                                    kind: 'task' as const,
                                })),
                            ]
                                .slice(0, 8)
                                .map((item) => (
                                    <Link
                                        key={`${item.kind}-${item.id}`}
                                        href={
                                            item.kind === 'deadline'
                                                ? matterShow(item.id)
                                                : item.matter_id === null
                                                  ? calendar()
                                                  : matterShow(item.matter_id)
                                        }
                                        className="flex items-center gap-3 py-3 transition-colors hover:bg-muted/40"
                                    >
                                        <span
                                            className={`mt-0.5 rounded-full p-2 ${item.is_overdue ? 'bg-red-100 text-red-700' : 'bg-orange-100 text-orange-700'}`}
                                        >
                                            {item.is_overdue ? (
                                                <AlertTriangle className="size-4" />
                                            ) : (
                                                <CalendarDays className="size-4" />
                                            )}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-medium">
                                                {item.kind === 'deadline'
                                                    ? item.title
                                                    : item.title}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {item.kind === 'deadline'
                                                    ? item.matter_number
                                                    : item.matter}{' '}
                                                · {item.client}
                                            </p>
                                        </div>
                                        <span
                                            className={`text-xs font-medium ${item.is_overdue ? 'text-red-700' : 'text-muted-foreground'}`}
                                        >
                                            {item.kind === 'deadline'
                                                ? item.deadline_at
                                                : item.due_at}
                                        </span>
                                    </Link>
                                ))}
                            {deadlines.length + tasks.length === 0 && (
                                <Empty
                                    label={
                                        sections.matters && sections.tasks
                                            ? 'No upcoming tasks or deadlines.'
                                            : sections.tasks
                                              ? 'No assigned tasks are due soon.'
                                              : 'No matter deadlines are due soon.'
                                    }
                                />
                            )}
                            </div>
                            </Panel>
                        )}
                        {sections.calendar && (
                            <Panel
                                title="Upcoming calendar"
                                action={{ title: 'View calendar', href: calendar() }}
                            >
                        <div className="divide-y">
                            {events.map((event) => (
                                <Link
                                    key={event.id}
                                    href={
                                        event.matter_id === null
                                            ? calendar()
                                            : matterShow(event.matter_id)
                                    }
                                    className="flex items-center gap-3 py-3 transition-colors hover:bg-muted/40"
                                >
                                    <span className="rounded-full bg-sky-100 p-2 text-sky-700">
                                        <CalendarDays className="size-4" />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-medium">
                                            {event.title}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {event.matter ?? 'Firm event'}
                                        </p>
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        {event.is_all_day
                                            ? 'All day'
                                            : dateFormatter.format(
                                                  new Date(event.starts_at),
                                              )}
                                    </span>
                                </Link>
                            ))}
                            {events.length === 0 && (
                                <Empty label="No events scheduled in the next seven days." />
                            )}
                        </div>
                            </Panel>
                        )}
                    </section>
                )}
                {(sections.payments || sections.activity) && (
                    <section className="grid gap-6 xl:grid-cols-2">
                        {sections.payments && (
                            <Panel
                                title="Recent payments"
                                action={{ title: 'View payments', href: payments() }}
                            >
                        <div className="divide-y">
                            {recentPayments.map((payment) => (
                                <Link
                                    key={payment.id}
                                    href={payments()}
                                    className="flex items-center justify-between gap-3 py-3 transition-colors hover:bg-muted/40"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {payment.payment_number}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {payment.invoice_number} ·{' '}
                                            {payment.client}
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className="font-medium">
                                            {formatMoney(
                                                payment.amount,
                                                payment.currency,
                                            )}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {payment.received_at}
                                        </p>
                                    </div>
                                </Link>
                            ))}
                            {recentPayments.length === 0 && (
                                <Empty label="No payments have been recorded." />
                            )}
                        </div>
                            </Panel>
                        )}
                        {sections.activity && (
                            <Panel
                                title="Recent activity"
                                action={{ title: 'View audit logs', href: auditLogs() }}
                            >
                        <div className="divide-y">
                            {activity.map((item) => (
                                <Link
                                    key={item.id}
                                    href={
                                        item.matter_id === null
                                            ? auditLogs()
                                            : matterShow(item.matter_id)
                                    }
                                    className="block py-3 transition-colors hover:bg-muted/40"
                                >
                                    <p className="text-sm font-medium">
                                        {item.description ?? item.event}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {item.actor} ·{' '}
                                        {item.matter ?? item.client ?? 'Firm'} ·{' '}
                                        {new Date(
                                            item.created_at,
                                        ).toLocaleString()}
                                    </p>
                                </Link>
                            ))}
                            {activity.length === 0 && (
                                <Empty label="No audit activity has been recorded." />
                            )}
                        </div>
                            </Panel>
                        )}
                    </section>
                )}
            </div>
        </>
    );
}

function Panel({
    title,
    action,
    children,
}: {
    title: string;
    action?: { title: string; href: RouteDefinition<'get'> };
    children: React.ReactNode;
}) {
    return (
        <section className="rounded-xl border bg-background p-5">
            <div className="mb-2 flex items-center justify-between gap-3">
                <h2 className="font-semibold">{title}</h2>
                {action && (
                    <Link
                        href={action.href}
                        className="text-sm font-medium text-primary hover:underline"
                    >
                        {action.title}
                    </Link>
                )}
            </div>
            {children}
        </section>
    );
}
function Empty({ label }: { label: string }) {
    return (
        <p className="py-8 text-center text-sm text-muted-foreground">
            {label}
        </p>
    );
}
Dashboard.layout = { breadcrumbs: [{ title: 'Dashboard', href: dashboard() }] };
