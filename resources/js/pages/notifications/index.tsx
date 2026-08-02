import { Head, Link, router } from '@inertiajs/react';
import {
    BellRing,
    CheckCheck,
    ChevronLeft,
    ChevronRight,
    CircleDollarSign,
    FileText,
    ReceiptText,
    ShieldCheck,
} from 'lucide-react';
import type { MouseEvent } from 'react';
import { Button } from '@/components/ui/button';
import { index as notifications, read, readAll } from '@/routes/notifications';

type Alert = {
    id: string;
    title: string;
    body: string;
    url: string;
    type: string;
    read_at: string | null;
    created_at: string;
};

type Props = {
    notificationHistory: {
        data: Alert[];
        from: number | null;
        to: number | null;
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { filter: 'all' | 'unread' };
    counts: { all: number; unread: number };
};

const notificationIcon = {
    document: FileText,
    invoice: ReceiptText,
    payment: CircleDollarSign,
    task: CheckCheck,
} as const;

export default function Notifications({
    notificationHistory,
    filters,
    counts,
}: Props) {
    const handleNotificationClick = (event: MouseEvent, alert: Alert) => {
        if (alert.read_at !== null) {
            return;
        }

        event.preventDefault();

        router.patch(
            read(alert.id).url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => router.visit(alert.url),
            },
        );
    };

    return (
        <>
            <Head title="Notifications" />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Personal alerts
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Notifications
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Keep track of work assignments, billing changes, and
                            matter activity that needs your attention.
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        disabled={counts.unread === 0}
                        onClick={() =>
                            router.patch(
                                readAll().url,
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <CheckCheck />
                        Mark all as read
                    </Button>
                </header>

                <section className="grid gap-3 sm:grid-cols-2">
                    <SummaryCard
                        label="Unread alerts"
                        value={counts.unread}
                        detail="Require your review"
                        tone="bg-orange-50 text-orange-700 dark:bg-orange-950/20 dark:text-orange-300"
                    />
                    <SummaryCard
                        label="All notifications"
                        value={counts.all}
                        detail="Recorded in your inbox"
                        tone="bg-primary/8 text-primary dark:bg-primary/20"
                    />
                </section>

                <section className="overflow-hidden rounded-xl border bg-background">
                    <div className="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-1 rounded-lg bg-muted p-1">
                            <FilterLink
                                active={filters.filter === 'all'}
                                href={notifications({
                                    query: { filter: 'all' },
                                })}
                                label={`All (${counts.all})`}
                            />
                            <FilterLink
                                active={filters.filter === 'unread'}
                                href={notifications({
                                    query: { filter: 'unread' },
                                })}
                                label={`Unread (${counts.unread})`}
                            />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {notificationHistory.total} notification
                            {notificationHistory.total === 1 ? '' : 's'}
                        </p>
                    </div>

                    <div className="divide-y">
                        {notificationHistory.data.map((alert) => {
                            const Icon =
                                notificationIcon[
                                    alert.type as keyof typeof notificationIcon
                                ] ?? BellRing;

                            return (
                                <Link
                                    key={alert.id}
                                    href={alert.url}
                                    onClick={(event) =>
                                        handleNotificationClick(event, alert)
                                    }
                                    className={`group flex gap-4 p-5 transition-colors hover:bg-muted/40 ${alert.read_at === null ? 'bg-orange-50/35 dark:bg-orange-950/10' : ''}`}
                                >
                                    <span
                                        className={`grid size-10 shrink-0 place-items-center rounded-full ${alert.read_at === null ? 'bg-orange-100 text-orange-700 dark:bg-orange-950/40 dark:text-orange-300' : 'bg-muted text-muted-foreground'}`}
                                    >
                                        <Icon className="size-4.5" />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <p className="font-medium">
                                                {alert.title}
                                            </p>
                                            {alert.read_at === null && (
                                                <span className="rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-800 dark:bg-orange-950/40 dark:text-orange-200">
                                                    New
                                                </span>
                                            )}
                                        </div>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {alert.body}
                                        </p>
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            {new Date(
                                                alert.created_at,
                                            ).toLocaleString()}
                                        </p>
                                    </div>
                                    <ChevronRight className="mt-2 size-4 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
                                </Link>
                            );
                        })}
                        {notificationHistory.data.length === 0 && (
                            <div className="flex flex-col items-center px-6 py-16 text-center">
                                <span className="grid size-12 place-items-center rounded-full bg-muted text-muted-foreground">
                                    <ShieldCheck className="size-6" />
                                </span>
                                <h2 className="mt-4 font-medium">
                                    You are all caught up
                                </h2>
                                <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                                    New assignments and practice activity will
                                    appear here when they need your attention.
                                </p>
                            </div>
                        )}
                    </div>

                    {notificationHistory.last_page > 1 && (
                        <div className="flex items-center justify-between border-t p-4">
                            <p className="text-sm text-muted-foreground">
                                Showing {notificationHistory.from}–
                                {notificationHistory.to} of{' '}
                                {notificationHistory.total}
                            </p>
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={
                                        notificationHistory.prev_page_url ===
                                        null
                                    }
                                    onClick={() =>
                                        notificationHistory.prev_page_url !==
                                            null &&
                                        router.visit(
                                            notificationHistory.prev_page_url,
                                        )
                                    }
                                >
                                    <ChevronLeft />
                                    Previous
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={
                                        notificationHistory.next_page_url ===
                                        null
                                    }
                                    onClick={() =>
                                        notificationHistory.next_page_url !==
                                            null &&
                                        router.visit(
                                            notificationHistory.next_page_url,
                                        )
                                    }
                                >
                                    Next
                                    <ChevronRight />
                                </Button>
                            </div>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

function SummaryCard({
    label,
    value,
    detail,
    tone,
}: {
    label: string;
    value: number;
    detail: string;
    tone: string;
}) {
    return (
        <div className={`rounded-xl p-5 ${tone}`}>
            <p className="text-sm font-medium">{label}</p>
            <p className="mt-2 text-3xl font-semibold">{value}</p>
            <p className="mt-1 text-sm opacity-75">{detail}</p>
        </div>
    );
}

function FilterLink({
    active,
    href,
    label,
}: {
    active: boolean;
    href: ReturnType<typeof notifications>;
    label: string;
}) {
    return (
        <Link
            href={href}
            className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${active ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}
        >
            {label}
        </Link>
    );
}

Notifications.layout = {
    breadcrumbs: [{ title: 'Notifications', href: notifications() }],
};
