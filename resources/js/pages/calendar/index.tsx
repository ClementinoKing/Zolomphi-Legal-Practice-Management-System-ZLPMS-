import { Head, Link } from '@inertiajs/react';
import {
    ArrowUpRight,
    ChevronLeft,
    ChevronRight,
    Clock3,
    ListTodo,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

type CalendarEvent = {
    id: string;
    kind: 'event' | 'task';
    title: string;
    starts_at: string;
    ends_at: string | null;
    is_all_day: boolean;
    type: string;
    status: string;
    matter_id: number | null;
    matter_number: string | null;
    matter_title: string | null;
    client: string | null;
};

type Props = { month: string; events: CalendarEvent[] };

const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const maxVisibleDayEvents = 2;

export default function CalendarIndex({ month, events }: Props) {
    const [kind, setKind] = useState<'all' | CalendarEvent['kind']>('all');
    const currentMonth = useMemo(() => monthDate(month), [month]);
    const filteredEvents = events.filter(
        (event) => kind === 'all' || event.kind === kind,
    );
    const eventsByDate = useMemo(
        () =>
            filteredEvents.reduce<Record<string, CalendarEvent[]>>(
                (grouped, event) => {
                    const date = event.starts_at.slice(0, 10);
                    grouped[date] = [...(grouped[date] ?? []), event];

                    return grouped;
                },
                {},
            ),
        [filteredEvents],
    );
    const days = calendarDays(currentMonth);
    const previousMonth = shiftMonth(currentMonth, -1);
    const nextMonth = shiftMonth(currentMonth, 1);

    return (
        <>
            <Head title="Calendar" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-4 border-b pb-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-sm font-medium text-muted-foreground">
                            Practice calendar
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            All activities
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Matter meetings, hearings, reminders, and task
                            deadlines in one place.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button asChild size="icon" variant="outline">
                            <Link
                                href={calendarHref(previousMonth)}
                                aria-label="Previous month"
                            >
                                <ChevronLeft />
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={calendarHref(todayMonth())}>Today</Link>
                        </Button>
                        <Button asChild size="icon" variant="outline">
                            <Link
                                href={calendarHref(nextMonth)}
                                aria-label="Next month"
                            >
                                <ChevronRight />
                            </Link>
                        </Button>
                    </div>
                </header>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 className="text-xl font-semibold">
                        {monthLabel(currentMonth)}
                    </h2>
                    <div className="flex rounded-lg bg-muted p-1">
                        {(['all', 'event', 'task'] as const).map((option) => (
                            <Button
                                key={option}
                                type="button"
                                size="sm"
                                variant={
                                    kind === option ? 'secondary' : 'ghost'
                                }
                                onClick={() => setKind(option)}
                            >
                                {option === 'all'
                                    ? 'All'
                                    : option === 'event'
                                      ? 'Events'
                                      : 'Deadlines'}
                            </Button>
                        ))}
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border border-border/70 bg-background">
                    <div className="grid grid-cols-7 border-b bg-muted/50">
                        {weekdays.map((day) => (
                            <div
                                key={day}
                                className="px-3 py-3 text-center text-xs font-medium text-muted-foreground uppercase"
                            >
                                {day}
                            </div>
                        ))}
                    </div>
                    <div className="grid grid-cols-7">
                        {days.map((day) => {
                            const date = dateKey(day);
                            const dayEvents = eventsByDate[date] ?? [];
                            const isCurrentMonth =
                                day.getMonth() === currentMonth.getMonth();
                            const dayCell = (
                                <div className="min-h-30 min-w-0 border-r border-b p-2 last:border-r-0 sm:min-h-36">
                                    <div className="mb-2 flex items-center justify-between">
                                        <span
                                            className={`flex size-6 items-center justify-center rounded-full text-xs font-medium ${isToday(day) ? 'bg-primary text-primary-foreground' : isCurrentMonth ? 'text-foreground' : 'text-muted-foreground'}`}
                                        >
                                            {day.getDate()}
                                        </span>
                                        {dayEvents.length > 0 && (
                                            <span className="text-[10px] text-muted-foreground">
                                                {dayEvents.length}
                                            </span>
                                        )}
                                    </div>
                                    <div className="grid min-w-0 gap-1">
                                        {dayEvents
                                            .slice(0, maxVisibleDayEvents)
                                            .map((event) => (
                                                <CalendarItem
                                                    key={event.id}
                                                    event={event}
                                                    compact
                                                />
                                            ))}
                                        {dayEvents.length >
                                            maxVisibleDayEvents && (
                                            <p className="px-1 text-xs text-muted-foreground">
                                                +
                                                {dayEvents.length -
                                                    maxVisibleDayEvents}{' '}
                                                more
                                            </p>
                                        )}
                                    </div>
                                </div>
                            );

                            return (
                                <DayActivityPopover
                                    key={date}
                                    date={day}
                                    events={dayEvents}
                                >
                                    {dayCell}
                                </DayActivityPopover>
                            );
                        })}
                    </div>
                </div>

                <section className="grid gap-4 lg:grid-cols-[1fr_20rem]">
                    <div className="rounded-xl border border-border/70 bg-background p-5">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <h2 className="font-semibold">
                                    Scheduled this month
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {filteredEvents.length} activities shown
                                </p>
                            </div>
                            <Badge variant="outline">
                                {kind === 'all'
                                    ? 'All activity'
                                    : kind === 'event'
                                      ? 'Events'
                                      : 'Deadlines'}
                            </Badge>
                        </div>
                        <div className="mt-4 divide-y">
                            {filteredEvents.length === 0 ? (
                                <p className="py-10 text-center text-sm text-muted-foreground">
                                    No activities scheduled for this month.
                                </p>
                            ) : (
                                filteredEvents.map((event) => (
                                    <div
                                        key={event.id}
                                        className="py-3 first:pt-0 last:pb-0"
                                    >
                                        <CalendarItem event={event} />
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                    <aside className="rounded-xl border border-border/70 bg-muted/30 p-5">
                        <h2 className="font-semibold">Calendar guide</h2>
                        <div className="mt-4 grid gap-3 text-sm text-muted-foreground">
                            <p className="flex items-center gap-2">
                                <span className="size-2 rounded-full bg-primary" />{' '}
                                Meetings, hearings, reminders
                            </p>
                            <p className="flex items-center gap-2">
                                <span className="size-2 rounded-full bg-amber-500" />{' '}
                                Task deadlines
                            </p>
                            <p className="flex items-center gap-2">
                                <span className="size-2 rounded-full bg-destructive" />{' '}
                                Overdue deadlines
                            </p>
                            <p>
                                Open any activity to continue work in its matter
                                workspace.
                            </p>
                        </div>
                    </aside>
                </section>
            </div>
        </>
    );
}

function DayActivityPopover({
    date,
    events,
    children,
}: {
    date: Date;
    events: CalendarEvent[];
    children: React.ReactNode;
}) {
    if (events.length === 0) {
        return children;
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>{children}</TooltipTrigger>
            <TooltipContent
                side="top"
                align="start"
                sideOffset={10}
                className="w-80 max-w-[calc(100vw-2rem)] rounded-xl border border-border bg-popover p-0 text-popover-foreground shadow-xl [&>svg]:hidden"
            >
                <div className="border-b border-border px-4 py-3">
                    <div className="flex items-center justify-between gap-3">
                        <p className="font-semibold">{dayLabel(date)}</p>
                        <Badge variant="secondary">
                            {events.length === 1
                                ? '1 activity'
                                : `${events.length} activities`}
                        </Badge>
                    </div>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        Matters and deadlines scheduled for this day.
                    </p>
                </div>
                <div className="max-h-72 overflow-y-auto p-2">
                    {events.map((event) =>
                        event.matter_id ? (
                            <Link
                                key={event.id}
                                href={`/matters/${event.matter_id}`}
                                className="group flex gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-muted/70 focus-visible:bg-muted/70 focus-visible:outline-none"
                            >
                                <span
                                    className={`mt-1.5 size-2 shrink-0 rounded-full ${eventDotClassName(event)}`}
                                />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium">
                                        {event.title}
                                    </p>
                                    <p className="mt-0.5 truncate text-xs text-muted-foreground">
                                        {event.matter_number} ·{' '}
                                        {event.client ?? 'Client'}
                                    </p>
                                </div>
                                <ArrowUpRight className="mt-0.5 size-4 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100" />
                            </Link>
                        ) : (
                            <div
                                key={event.id}
                                className="flex gap-3 rounded-lg px-2 py-2"
                            >
                                <span
                                    className={`mt-1.5 size-2 shrink-0 rounded-full ${eventDotClassName(event)}`}
                                />
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium">
                                        {event.title}
                                    </p>
                                    <p className="mt-0.5 truncate text-xs text-muted-foreground">
                                        General activity
                                    </p>
                                </div>
                            </div>
                        ),
                    )}
                </div>
            </TooltipContent>
        </Tooltip>
    );
}

function CalendarItem({
    event,
    compact = false,
}: {
    event: CalendarEvent;
    compact?: boolean;
}) {
    const isOverdue = isOverdueDeadline(event);
    const className = isOverdue
        ? 'border-destructive/30 bg-destructive/10 text-destructive'
        : event.kind === 'task'
          ? 'border-amber-500/30 bg-amber-500/10 text-amber-950 dark:text-amber-200'
          : 'border-primary/20 bg-primary/10 text-primary';
    const content = (
        <>
            {!event.is_all_day && <Clock3 className="size-3 shrink-0" />}
            {event.kind === 'task' && <ListTodo className="size-3 shrink-0" />}
            <span className="min-w-0 flex-1 truncate">{event.title}</span>
        </>
    );

    if (compact) {
        return event.matter_id ? (
            <Link
                href={`/matters/${event.matter_id}`}
                className={`flex w-full max-w-full min-w-0 items-center gap-1 overflow-hidden rounded border px-1.5 py-1 text-[11px] font-medium ${className}`}
                title={event.title}
            >
                {content}
            </Link>
        ) : (
            <div
                className={`flex w-full max-w-full min-w-0 items-center gap-1 overflow-hidden rounded border px-1.5 py-1 text-[11px] font-medium ${className}`}
            >
                {content}
            </div>
        );
    }

    return event.matter_id ? (
        <Link
            href={`/matters/${event.matter_id}`}
            className="block rounded-lg p-2 transition-colors hover:bg-muted/60"
        >
            <div className="flex items-start justify-between gap-4">
                <div className="min-w-0">
                    <p className="font-medium">{event.title}</p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {event.matter_number} · {event.matter_title} ·{' '}
                        {event.client ?? 'Client'}
                    </p>
                </div>
                <div className="shrink-0 text-right text-xs text-muted-foreground">
                    <p>{formatEventDate(event)}</p>
                    <Badge
                        className="mt-1"
                        variant={isOverdue ? 'destructive' : 'outline'}
                    >
                        {isOverdue
                            ? 'Overdue'
                            : event.kind === 'task'
                              ? 'Deadline'
                              : event.type}
                    </Badge>
                </div>
            </div>
        </Link>
    ) : null;
}

function monthDate(month: string): Date {
    return new Date(`${month}-01T12:00:00`);
}
function shiftMonth(month: Date, amount: number): Date {
    return new Date(month.getFullYear(), month.getMonth() + amount, 1, 12);
}
function calendarHref(month: Date): string {
    return `/calendar?month=${month.getFullYear()}-${String(month.getMonth() + 1).padStart(2, '0')}`;
}
function todayMonth(): Date {
    return new Date();
}
function monthLabel(month: Date): string {
    return new Intl.DateTimeFormat('en-GB', {
        month: 'long',
        year: 'numeric',
    }).format(month);
}
function dayLabel(day: Date): string {
    return new Intl.DateTimeFormat('en-GB', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    }).format(day);
}
function calendarDays(month: Date): Date[] {
    const first = new Date(month.getFullYear(), month.getMonth(), 1, 12);
    const start = new Date(first);
    start.setDate(first.getDate() - first.getDay());

    return Array.from({ length: 42 }, (_, index) => {
        const day = new Date(start);
        day.setDate(start.getDate() + index);

        return day;
    });
}
function dateKey(value: Date): string {
    return `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, '0')}-${String(value.getDate()).padStart(2, '0')}`;
}
function isToday(value: Date): boolean {
    return dateKey(value) === dateKey(new Date());
}
function isOverdueDeadline(event: CalendarEvent): boolean {
    return (
        event.kind === 'task' &&
        !['cancelled', 'closed', 'completed'].includes(event.status) &&
        event.starts_at.slice(0, 10) < dateKey(new Date())
    );
}
function eventDotClassName(event: CalendarEvent): string {
    if (isOverdueDeadline(event)) {
        return 'bg-destructive';
    }

    return event.kind === 'task' ? 'bg-amber-500' : 'bg-primary';
}
function formatEventDate(event: CalendarEvent): string {
    const date = new Date(event.starts_at);
    const day = new Intl.DateTimeFormat('en-GB', {
        day: 'numeric',
        month: 'short',
    }).format(date);

    return event.is_all_day
        ? day
        : `${day}, ${new Intl.DateTimeFormat('en-GB', { timeStyle: 'short' }).format(date)}`;
}

CalendarIndex.layout = {
    breadcrumbs: [{ title: 'Calendar', href: '/calendar' }],
};
