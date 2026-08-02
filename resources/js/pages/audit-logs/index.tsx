import { Form, Head, Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as auditLogs } from '@/routes/audit-logs';
import { show as matterShow } from '@/routes/matters';
type Log = {
    id: number;
    event: string;
    description: string | null;
    actor: string;
    client: string | null;
    matter: string | null;
    matter_id: number | null;
    created_at: string;
};
type Props = {
    logs: {
        data: Log[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: {
        search: string;
        event: string;
        start_date: string;
        end_date: string;
    };
    events: string[];
};
export default function AuditLogs({ logs, filters, events }: Props) {
    return (
        <>
            <Head title="Audit logs" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
                <header>
                    <p className="text-sm font-medium text-primary">
                        Firm oversight
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Audit logs
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Trace important changes across the practice workspace.
                    </p>
                </header>
                <Form
                    action={auditLogs().url}
                    method="get"
                    className="grid gap-3 rounded-xl bg-muted/40 p-3 md:grid-cols-[minmax(0,1fr)_11rem_10rem_10rem_auto]"
                >
                    <div className="relative">
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            className="bg-background pl-9"
                            name="search"
                            defaultValue={filters.search}
                            placeholder="Search event or description"
                        />
                    </div>
                    <select
                        name="event"
                        defaultValue={filters.event}
                        className="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">All events</option>
                        {events.map((event) => (
                            <option key={event} value={event}>
                                {event}
                            </option>
                        ))}
                    </select>
                    <Input
                        name="start_date"
                        type="date"
                        defaultValue={filters.start_date}
                    />
                    <Input
                        name="end_date"
                        type="date"
                        defaultValue={filters.end_date}
                    />
                    <Button type="submit">Filter</Button>
                </Form>
                <section className="overflow-x-auto rounded-xl border bg-background">
                    <table className="w-full min-w-220 text-left text-sm">
                        <thead className="bg-muted/50 text-xs text-muted-foreground uppercase">
                            <tr>
                                <th className="px-4 py-3">Event</th>
                                <th className="px-4 py-3">Actor</th>
                                <th className="px-4 py-3">Client / matter</th>
                                <th className="px-4 py-3">When</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {logs.data.map((log) => (
                                <tr key={log.id} className="hover:bg-muted/30">
                                    <td className="px-4 py-3">
                                        <p className="font-medium">
                                            {log.description ?? log.event}
                                        </p>
                                        <p className="mt-1 font-mono text-xs text-muted-foreground">
                                            {log.event}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3">{log.actor}</td>
                                    <td className="px-4 py-3">
                                        {log.matter_id === null ? (
                                            <span>{log.client ?? 'Firm'}</span>
                                        ) : (
                                            <Link
                                                href={matterShow(log.matter_id)}
                                                className="text-primary hover:underline"
                                            >
                                                {log.matter}
                                                {log.client
                                                    ? ` · ${log.client}`
                                                    : ''}
                                            </Link>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {new Date(
                                            log.created_at,
                                        ).toLocaleString()}
                                    </td>
                                </tr>
                            ))}
                            {logs.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        No audit records match these filters.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </section>
                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <p>
                        {logs.total === 0
                            ? 'No records'
                            : `Showing ${logs.from}–${logs.to} of ${logs.total}`}
                    </p>
                    <div className="flex gap-1">
                        {logs.links.map(
                            (link) =>
                                link.url && (
                                    <Button
                                        key={link.label}
                                        size="sm"
                                        variant={
                                            link.active ? 'default' : 'outline'
                                        }
                                        asChild
                                    >
                                        <Link
                                            href={link.url}
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    </Button>
                                ),
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
AuditLogs.layout = {
    breadcrumbs: [{ title: 'Audit logs', href: auditLogs() }],
};
