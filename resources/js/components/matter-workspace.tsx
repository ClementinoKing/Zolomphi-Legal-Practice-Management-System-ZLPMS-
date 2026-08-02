import { Form, Link } from '@inertiajs/react';
import { Check, Plus } from 'lucide-react';
import { useState } from 'react';
import SearchableMultiSelect from '@/components/searchable-multi-select';
import SearchableSelect from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useCurrency } from '@/hooks/use-currency';
import type { CustomFieldDefinition } from '@/types/custom-fields';

type User = { id: number; name: string; email: string };
type FormValues = Record<
    string,
    string | number | boolean | null | undefined | number[] | string[]
>;
type Document = {
    id: number;
    name: string;
    original_filename: string;
    category: string;
    visibility: string;
    created_at: string;
    uploader?: User;
};
type Matter = {
    id: number;
    matter_number: string;
    title: string;
    description: string | null;
    status: string;
    priority: string;
    billing_type: string;
    budget_amount: string | null;
    default_hourly_rate: string | null;
    opened_at: string;
    deadline_at: string | null;
    client: {
        client_number: string;
        full_name: string | null;
        organization_name: string | null;
        email: string | null;
        phone: string | null;
    };
    matter_type: { name: string };
    responsible_user: User;
    attorneys: User[];
    tasks: Array<{
        id: number;
        title: string;
        description: string | null;
        status: string;
        priority: string;
        due_at: string | null;
        assignees: User[];
    }>;
    time_entries: Array<{
        id: number;
        entry_date: string;
        description: string;
        hours: string;
        hourly_rate: string | null;
        is_billable: boolean;
        status: string;
        user?: User;
    }>;
    documents: Document[];
    notes: Array<{
        id: number;
        body: string;
        created_at: string;
        author?: User;
    }>;
    emails: Array<{
        id: number;
        subject: string;
        to_recipients: string[];
        status: string;
        sent_at: string | null;
        sender?: User;
        documents: Document[];
    }>;
    appointments: Array<{
        id: number;
        title: string;
        description: string | null;
        type: string;
        starts_at: string;
        ends_at: string;
        location: string | null;
        is_all_day: boolean;
        attendees: User[];
    }>;
    expenses: Array<{
        id: number;
        expense_category_id: number;
        category: string;
        vendor: string | null;
        currency: string;
        amount: string;
        incurred_at: string;
        is_billable: boolean;
        status: string;
        description: string | null;
        receipt_path: string | null;
    }>;
    charges: Array<{
        id: number;
        billing_item_id: number | null;
        description: string;
        quantity: string;
        unit_price: string;
        tax_rate: string;
        amount: string;
        status: string;
        charged_at: string;
        billing_item?: { name: string };
    }>;
    metadata: {
        custom_fields?: Record<string, boolean | number | string>;
    } | null;
};

type Props = {
    matter: Matter;
    users: User[];
    expenseCategories: Array<{
        id: number;
        name: string;
        is_billable_by_default: boolean;
    }>;
    billingItems: Array<{
        id: number;
        name: string;
        default_amount: string;
        tax_rate: string;
    }>;
    activity: Array<{
        id: number;
        event: string;
        description: string | null;
        created_at: string;
        user?: User;
    }>;
    summary: {
        open_tasks: number;
        upcoming_events: number;
        billable_time_amount: number;
        expenses_amount: number;
        flat_fees_amount: number;
    };
    customFields: CustomFieldDefinition[];
};

type Tab =
    | 'overview'
    | 'tasks'
    | 'time'
    | 'documents'
    | 'notes'
    | 'emails'
    | 'calendar'
    | 'financials';
type Work =
    | 'tasks'
    | 'time-entries'
    | 'documents'
    | 'notes'
    | 'emails'
    | 'calendar-events'
    | 'expenses'
    | 'flat-fees';

const tabs: Array<{ id: Tab; label: string }> = [
    { id: 'overview', label: 'Overview' },
    { id: 'tasks', label: 'Tasks & deadlines' },
    { id: 'time', label: 'Time entries' },
    { id: 'documents', label: 'Documents' },
    { id: 'notes', label: 'Notes' },
    { id: 'emails', label: 'Emails' },
    { id: 'calendar', label: 'Calendar' },
    { id: 'financials', label: 'Financials' },
];

export default function MatterWorkspace(props: Props) {
    const [tab, setTab] = useState<Tab>('overview');
    const clientName =
        props.matter.client.full_name ??
        props.matter.client.organization_name ??
        'Client';

    return (
        <section className="grid gap-6">
            <header className="grid gap-4 border-b pb-5">
                <Link
                    href="/matters"
                    className="text-sm text-muted-foreground hover:text-foreground"
                >
                    ← All matters
                </Link>
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="grid gap-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="font-mono text-xs text-muted-foreground">
                                {props.matter.matter_number}
                            </p>
                            <Badge>
                                {props.matter.status.replace('_', ' ')}
                            </Badge>
                            <Badge variant="outline">
                                {props.matter.priority}
                            </Badge>
                        </div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {props.matter.title}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {clientName} · {props.matter.matter_type.name} ·{' '}
                            {props.matter.responsible_user.name}
                        </p>
                    </div>
                    <WorkDialog work="tasks" label="Add task" {...props} />
                </div>
            </header>

            <nav
                className="flex gap-1 overflow-x-auto rounded-xl bg-muted/50 p-1"
                aria-label="Matter workspace"
            >
                {tabs.map((item) => (
                    <Button
                        key={item.id}
                        type="button"
                        size="sm"
                        variant={tab === item.id ? 'secondary' : 'ghost'}
                        className="shrink-0"
                        onClick={() => setTab(item.id)}
                    >
                        {item.label}
                    </Button>
                ))}
            </nav>

            {tab === 'overview' && <Overview {...props} />}
            {tab === 'tasks' && <Tasks {...props} />}
            {tab === 'time' && <TimeEntries {...props} />}
            {tab === 'documents' && <Documents {...props} />}
            {tab === 'notes' && <Notes {...props} />}
            {tab === 'emails' && <Emails {...props} />}
            {tab === 'calendar' && <Calendar {...props} />}
            {tab === 'financials' && <Financials {...props} />}
        </section>
    );
}

function Overview({ matter, summary, activity, customFields }: Props) {
    const { formatMoney } = useCurrency();
    const customValues = matter.metadata?.custom_fields ?? {};
    const configuredFields = customFields.filter((field) =>
        Object.prototype.hasOwnProperty.call(customValues, field.key),
    );

    return (
        <div className="grid gap-6">
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <Metric label="Open tasks" value={String(summary.open_tasks)} />
                <Metric
                    label="Upcoming events"
                    value={String(summary.upcoming_events)}
                />
                <Metric
                    label="Billable time"
                    value={formatMoney(summary.billable_time_amount)}
                />
                <Metric
                    label="Expenses"
                    value={formatMoney(summary.expenses_amount)}
                />
                <Metric
                    label="Flat fees"
                    value={formatMoney(summary.flat_fees_amount)}
                />
            </div>
            <div className="grid gap-5 lg:grid-cols-[1.15fr_.85fr]">
                <section className="rounded-xl border border-border/70 bg-background p-5">
                    <h2 className="font-semibold">Matter details</h2>
                    <dl className="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                        <Detail
                            label="Client"
                            value={
                                matter.client.full_name ??
                                matter.client.organization_name ??
                                '—'
                            }
                        />
                        <Detail
                            label="Billing"
                            value={matter.billing_type.replace('_', ' ')}
                        />
                        <Detail
                            label="Budget"
                            value={
                                matter.budget_amount
                                    ? formatMoney(Number(matter.budget_amount))
                                    : 'Not set'
                            }
                        />
                        <Detail
                            label="Opened"
                            value={formatDate(matter.opened_at)}
                        />
                        <Detail
                            label="Matter deadline"
                            value={
                                matter.deadline_at
                                    ? formatDate(matter.deadline_at)
                                    : 'Not set'
                            }
                        />
                        <Detail
                            label="Attorney team"
                            value={
                                matter.attorneys
                                    .map((attorney) => attorney.name)
                                    .join(', ') || 'Responsible attorney only'
                            }
                        />
                    </dl>
                    {configuredFields.length > 0 && (
                        <>
                            <h3 className="mt-6 border-t pt-5 font-semibold">
                                Additional information
                            </h3>
                            <dl className="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                                {configuredFields.map((field) => (
                                    <Detail
                                        key={field.key}
                                        label={field.label}
                                        value={formatCustomValue(
                                            customValues[field.key],
                                            field.type,
                                        )}
                                    />
                                ))}
                            </dl>
                        </>
                    )}
                </section>
                <section className="rounded-xl border border-border/70 bg-background p-5">
                    <h2 className="font-semibold">Recent activity</h2>
                    <div className="mt-4 grid gap-4">
                        {activity.length === 0 ? (
                            <Empty label="Activity will appear as work is recorded." />
                        ) : (
                            activity.map((item) => (
                                <div key={item.id} className="text-sm">
                                    <p>{item.description ?? item.event}</p>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {item.user?.name ?? 'System'} ·{' '}
                                        {formatDateTime(item.created_at)}
                                    </p>
                                </div>
                            ))
                        )}
                    </div>
                </section>
            </div>
        </div>
    );
}

function formatCustomValue(
    value: boolean | number | string | undefined,
    type: CustomFieldDefinition['type'],
): string {
    if (value === undefined || value === '') {
        return '—';
    }

    if (type === 'boolean') {
        return value === true || value === 1 || value === '1' ? 'Yes' : 'No';
    }

    return String(value);
}

function Tasks(props: Props) {
    return (
        <WorkSection
            title="Tasks & deadlines"
            description="Assign legal work, track deadlines, and mark completed work."
            action={<WorkDialog work="tasks" label="Add task" {...props} />}
        >
            <Table
                headers={[
                    'Task',
                    'Assignees',
                    'Deadline',
                    'Priority',
                    'Status',
                    '',
                ]}
                empty="No tasks have been added."
            >
                {props.matter.tasks.map((task) => (
                    <tr
                        key={task.id}
                        className="transition-colors hover:bg-muted/30"
                    >
                        <td className="px-4 py-3">
                            <p className="font-medium">{task.title}</p>
                            {task.description && (
                                <p className="mt-0.5 text-muted-foreground">
                                    {task.description}
                                </p>
                            )}
                        </td>
                        <td className="px-4 py-3 text-muted-foreground">
                            {task.assignees
                                .map((user) => user.name)
                                .join(', ') || 'Unassigned'}
                        </td>
                        <td className="px-4 py-3 text-muted-foreground">
                            {task.due_at ? formatDate(task.due_at) : '—'}
                        </td>
                        <td className="px-4 py-3">
                            <Badge variant="outline">{task.priority}</Badge>
                        </td>
                        <td className="px-4 py-3">
                            <Badge
                                variant={
                                    task.status === 'completed'
                                        ? 'secondary'
                                        : 'default'
                                }
                            >
                                {task.status}
                            </Badge>
                        </td>
                        <td className="px-4 py-3 text-right">
                            <div className="flex justify-end gap-2">
                                <WorkDialog
                                    work="tasks"
                                    label="Edit"
                                    editing={{
                                        id: task.id,
                                        values: {
                                            title: task.title,
                                            description: task.description,
                                            due_at: task.due_at,
                                            priority: task.priority,
                                            assignee_ids: task.assignees.map(
                                                (user) => user.id,
                                            ),
                                        },
                                    }}
                                    {...props}
                                />
                                {task.status !== 'completed' && (
                                    <Form
                                        action={`/matters/${props.matter.id}/tasks/${task.id}/complete`}
                                        method="patch"
                                    >
                                        <Button size="sm" variant="outline">
                                            <Check /> Complete
                                        </Button>
                                    </Form>
                                )}
                            </div>
                        </td>
                    </tr>
                ))}
            </Table>
        </WorkSection>
    );
}

function TimeEntries(props: Props) {
    const { formatMoney } = useCurrency();

    return (
        <WorkSection
            title="Time entries"
            description="Record work performed against this matter."
            action={
                <WorkDialog work="time-entries" label="Add time" {...props} />
            }
        >
            <Table
                headers={[
                    'Date',
                    'Description',
                    'User',
                    'Hours',
                    'Rate',
                    'Status',
                    'Actions',
                ]}
                empty="No time entries have been recorded."
            >
                {props.matter.time_entries.map((entry) => (
                    <tr
                        key={entry.id}
                        className="transition-colors hover:bg-muted/30"
                    >
                        <td className="px-4 py-3 text-muted-foreground">
                            {formatDate(entry.entry_date)}
                        </td>
                        <td className="px-4 py-3">
                            <p className="font-medium">{entry.description}</p>
                            {entry.is_billable && (
                                <p className="text-xs text-muted-foreground">
                                    Billable
                                </p>
                            )}
                        </td>
                        <td className="px-4 py-3 text-muted-foreground">
                            {entry.user?.name ?? '—'}
                        </td>
                        <td className="px-4 py-3">{entry.hours}</td>
                        <td className="px-4 py-3">
                            {entry.hourly_rate
                                ? formatMoney(Number(entry.hourly_rate))
                                : '—'}
                        </td>
                        <td className="px-4 py-3">
                            <Badge variant="outline">{entry.status}</Badge>
                        </td>
                        <td className="px-4 py-3 text-right">
                            <WorkDialog
                                work="time-entries"
                                label="Edit"
                                editing={{
                                    id: entry.id,
                                    values: {
                                        entry_date: entry.entry_date,
                                        description: entry.description,
                                        hours: entry.hours,
                                        hourly_rate: entry.hourly_rate,
                                        is_billable: entry.is_billable,
                                        status: entry.status,
                                    },
                                }}
                                {...props}
                            />
                        </td>
                    </tr>
                ))}
            </Table>
        </WorkSection>
    );
}

function Documents(props: Props) {
    return (
        <WorkSection
            title="Documents"
            description="Private matter documents are available only to authorized firm staff."
            action={
                <WorkDialog
                    work="documents"
                    label="Upload document"
                    {...props}
                />
            }
        >
            <Table
                headers={[
                    'Document',
                    'Category',
                    'Visibility',
                    'Uploaded',
                    'Actions',
                ]}
                empty="No documents have been uploaded."
            >
                {props.matter.documents.map((document) => (
                    <tr
                        key={document.id}
                        className="transition-colors hover:bg-muted/30"
                    >
                        <td className="px-4 py-3">
                            <p className="font-medium">{document.name}</p>
                            <p className="text-xs text-muted-foreground">
                                {document.original_filename}
                            </p>
                        </td>
                        <td className="px-4 py-3 text-muted-foreground">
                            {document.category}
                        </td>
                        <td className="px-4 py-3">
                            <Badge variant="outline">
                                {document.visibility}
                            </Badge>
                        </td>
                        <td className="px-4 py-3 text-muted-foreground">
                            {document.uploader?.name ?? '—'} ·{' '}
                            {formatDate(document.created_at)}
                        </td>
                        <td className="px-4 py-3 text-right">
                            <div className="flex justify-end gap-2">
                                <WorkDialog
                                    work="documents"
                                    label="Edit"
                                    editing={{
                                        id: document.id,
                                        values: {
                                            name: document.name,
                                            category: document.category,
                                            visibility: document.visibility,
                                        },
                                    }}
                                    {...props}
                                />
                                <Button size="sm" variant="outline" asChild>
                                    <a
                                        href={`/matters/${props.matter.id}/documents/${document.id}/download`}
                                    >
                                        Download
                                    </a>
                                </Button>
                            </div>
                        </td>
                    </tr>
                ))}
            </Table>
        </WorkSection>
    );
}

function Notes(props: Props) {
    return (
        <WorkSection
            title="Notes"
            description="Internal notes stay with the matter audit trail."
            action={<WorkDialog work="notes" label="Add note" {...props} />}
        >
            <div className="grid gap-3">
                {props.matter.notes.length === 0 ? (
                    <Empty label="No internal notes have been added." />
                ) : (
                    props.matter.notes.map((note) => (
                        <article
                            key={note.id}
                            className="rounded-xl border border-border/70 bg-background p-4"
                        >
                            <p className="text-sm whitespace-pre-wrap">
                                {note.body}
                            </p>
                            <p className="mt-3 text-xs text-muted-foreground">
                                {note.author?.name ?? 'System'} ·{' '}
                                {formatDateTime(note.created_at)}
                            </p>
                            <div className="mt-3">
                                <WorkDialog
                                    work="notes"
                                    label="Edit"
                                    editing={{
                                        id: note.id,
                                        values: { body: note.body },
                                    }}
                                    {...props}
                                />
                            </div>
                        </article>
                    ))
                )}
            </div>
        </WorkSection>
    );
}

function Emails(props: Props) {
    return (
        <WorkSection
            title="Emails"
            description="Compose correspondence through the configured firm mailer and retain it with the matter."
            action={
                <WorkDialog work="emails" label="Compose email" {...props} />
            }
        >
            <Table
                headers={[
                    'Subject',
                    'Recipients',
                    'Attachments',
                    'Sent by',
                    'Status',
                ]}
                empty="No email correspondence has been recorded."
            >
                {props.matter.emails.map((email) => (
                    <tr
                        key={email.id}
                        className="transition-colors hover:bg-muted/30"
                    >
                        <td className="px-4 py-3 font-medium">
                            {email.subject}
                        </td>
                        <td className="px-4 py-3 text-muted-foreground">
                            {email.to_recipients.join(', ')}
                        </td>
                        <td className="px-4 py-3 text-muted-foreground">
                            {email.documents.length}
                        </td>
                        <td className="px-4 py-3 text-muted-foreground">
                            {email.sender?.name ?? '—'}
                        </td>
                        <td className="px-4 py-3">
                            <Badge
                                variant={
                                    email.status === 'sent'
                                        ? 'default'
                                        : 'secondary'
                                }
                            >
                                {email.status}
                            </Badge>
                        </td>
                    </tr>
                ))}
            </Table>
        </WorkSection>
    );
}

function Calendar(props: Props) {
    return (
        <WorkSection
            title="Calendar events"
            description="Schedule meetings, hearings, deadlines, and reminders for this matter."
            action={
                <WorkDialog
                    work="calendar-events"
                    label="Add event"
                    {...props}
                />
            }
        >
            <Table
                headers={[
                    'Event',
                    'When',
                    'Location',
                    'Attendees',
                    'Type',
                    'Actions',
                ]}
                empty="No calendar events have been scheduled."
            >
                {props.matter.appointments.map((event) => (
                    <tr
                        key={event.id}
                        className="transition-colors hover:bg-muted/30"
                    >
                        <td className="px-4 py-3 font-medium">{event.title}</td>
                        <td className="px-4 py-3 text-muted-foreground">
                            {event.is_all_day
                                ? formatDate(event.starts_at)
                                : `${formatDateTime(event.starts_at)} – ${formatDateTime(event.ends_at)}`}
                        </td>
                        <td className="px-4 py-3 text-muted-foreground">
                            {event.location ?? '—'}
                        </td>
                        <td className="px-4 py-3 text-muted-foreground">
                            {event.attendees
                                .map((user) => user.name)
                                .join(', ') || '—'}
                        </td>
                        <td className="px-4 py-3">
                            <Badge variant="outline">{event.type}</Badge>
                        </td>
                        <td className="px-4 py-3 text-right">
                            <WorkDialog
                                work="calendar-events"
                                label="Edit"
                                editing={{
                                    id: event.id,
                                    values: {
                                        title: event.title,
                                        description: event.description,
                                        type: event.type,
                                        location: event.location,
                                        starts_at: event.starts_at,
                                        ends_at: event.ends_at,
                                        is_all_day: event.is_all_day,
                                        attendee_ids: event.attendees.map(
                                            (user) => user.id,
                                        ),
                                    },
                                }}
                                {...props}
                            />
                        </td>
                    </tr>
                ))}
            </Table>
        </WorkSection>
    );
}

function Financials(props: Props) {
    const { formatMoney } = useCurrency();

    return (
        <div className="grid gap-6">
            <WorkSection
                title="Expenses"
                description="Record disbursements and attach receipts."
                action={
                    <WorkDialog
                        work="expenses"
                        label="Add expense"
                        {...props}
                    />
                }
            >
                <Table
                    headers={[
                        'Date',
                        'Category',
                        'Vendor',
                        'Amount',
                        'Billing',
                        'Receipt',
                        'Actions',
                    ]}
                    empty="No expenses have been recorded."
                >
                    {props.matter.expenses.map((expense) => (
                        <tr
                            key={expense.id}
                            className="transition-colors hover:bg-muted/30"
                        >
                            <td className="px-4 py-3 text-muted-foreground">
                                {formatDate(expense.incurred_at)}
                            </td>
                            <td className="px-4 py-3 font-medium">
                                {expense.category}
                            </td>
                            <td className="px-4 py-3 text-muted-foreground">
                                {expense.vendor ?? '—'}
                            </td>
                            <td className="px-4 py-3">
                                {formatMoney(
                                    Number(expense.amount),
                                    expense.currency,
                                )}
                            </td>
                            <td className="px-4 py-3">
                                <Badge
                                    variant={
                                        expense.is_billable
                                            ? 'default'
                                            : 'secondary'
                                    }
                                >
                                    {expense.is_billable
                                        ? 'Billable'
                                        : 'Internal'}
                                </Badge>
                            </td>
                            <td className="px-4 py-3">
                                {expense.receipt_path ? (
                                    <a
                                        href={`/matters/${props.matter.id}/expenses/${expense.id}/receipt`}
                                        className="font-medium text-primary hover:underline"
                                    >
                                        Download
                                    </a>
                                ) : (
                                    <span className="text-muted-foreground">
                                        —
                                    </span>
                                )}
                            </td>
                            <td className="px-4 py-3 text-right">
                                <WorkDialog
                                    work="expenses"
                                    label="Edit"
                                    editing={{
                                        id: expense.id,
                                        values: {
                                            expense_category_id:
                                                expense.expense_category_id,
                                            vendor: expense.vendor,
                                            amount: expense.amount,
                                            incurred_at: expense.incurred_at,
                                            is_billable: expense.is_billable,
                                            description: expense.description,
                                        },
                                    }}
                                    {...props}
                                />
                            </td>
                        </tr>
                    ))}
                </Table>
            </WorkSection>
            <WorkSection
                title="Flat fees"
                description="Create invoice-ready charges for agreed fixed-fee work."
                action={
                    <WorkDialog
                        work="flat-fees"
                        label="Add flat fee"
                        {...props}
                    />
                }
            >
                <Table
                    headers={[
                        'Date',
                        'Description',
                        'Billing item',
                        'Amount',
                        'Status',
                        'Actions',
                    ]}
                    empty="No flat fees have been recorded."
                >
                    {props.matter.charges.map((charge) => (
                        <tr
                            key={charge.id}
                            className="transition-colors hover:bg-muted/30"
                        >
                            <td className="px-4 py-3 text-muted-foreground">
                                {formatDate(charge.charged_at)}
                            </td>
                            <td className="px-4 py-3 font-medium">
                                {charge.description}
                            </td>
                            <td className="px-4 py-3 text-muted-foreground">
                                {charge.billing_item?.name ?? 'Custom fee'}
                            </td>
                            <td className="px-4 py-3">
                                {formatMoney(Number(charge.amount))}
                            </td>
                            <td className="px-4 py-3">
                                <Badge variant="outline">{charge.status}</Badge>
                            </td>
                            <td className="px-4 py-3 text-right">
                                <WorkDialog
                                    work="flat-fees"
                                    label="Edit"
                                    editing={{
                                        id: charge.id,
                                        values: {
                                            billing_item_id:
                                                charge.billing_item_id,
                                            description: charge.description,
                                            quantity: charge.quantity,
                                            unit_price: charge.unit_price,
                                            tax_rate: charge.tax_rate,
                                            charged_at: charge.charged_at,
                                            status: charge.status,
                                        },
                                    }}
                                    {...props}
                                />
                            </td>
                        </tr>
                    ))}
                </Table>
            </WorkSection>
        </div>
    );
}

function WorkSection({
    title,
    description,
    action,
    children,
}: {
    title: string;
    description: string;
    action: React.ReactNode;
    children: React.ReactNode;
}) {
    return (
        <section className="grid gap-5">
            <header className="flex flex-col gap-3 border-b pb-5 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 className="font-semibold">{title}</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {description}
                    </p>
                </div>
                {action}
            </header>
            {children}
        </section>
    );
}
function Table({
    headers,
    empty,
    children,
}: {
    headers: string[];
    empty: string;
    children: React.ReactNode;
}) {
    const hasRows = Array.isArray(children)
        ? children.length > 0
        : Boolean(children);

    return (
        <div className="overflow-x-auto rounded-xl border border-border/70 bg-background">
            <table className="w-full min-w-200 text-left text-sm">
                <thead className="bg-muted/50 text-xs tracking-wide text-muted-foreground uppercase">
                    <tr>
                        {headers.map((header) => (
                            <th key={header} className="px-4 py-3 font-medium">
                                {header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {hasRows ? (
                        children
                    ) : (
                        <tr>
                            <td
                                colSpan={headers.length}
                                className="px-4 py-12 text-center text-muted-foreground"
                            >
                                {empty}
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}
function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl border border-border/70 bg-background p-4">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-lg font-semibold">{value}</p>
        </div>
    );
}
function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="mt-1 font-medium">{value}</dd>
        </div>
    );
}
function Empty({ label }: { label: string }) {
    return (
        <p className="py-8 text-center text-sm text-muted-foreground">
            {label}
        </p>
    );
}

function WorkDialog(
    props: Props & {
        work: Work;
        label: string;
        editing?: { id: number; values: FormValues };
    },
) {
    const [open, setOpen] = useState(false);
    const isEditing = props.editing !== undefined;
    const title = isEditing ? `Edit ${props.label.toLowerCase()}` : props.label;

    return (
        <Dialog modal={false} open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    size={isEditing ? 'sm' : 'default'}
                    variant={isEditing ? 'outline' : 'default'}
                >
                    {!isEditing && <Plus />}
                    {props.label}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>
                        Record work against {props.matter.matter_number}.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/matters/${props.matter.id}/${props.work}${isEditing ? `/${props.editing?.id}` : ''}`}
                    method={isEditing ? 'patch' : 'post'}
                    encType="multipart/form-data"
                    className="grid gap-5"
                    onSuccess={() => setOpen(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <WorkFields {...props} errors={errors} />
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button disabled={processing}>
                                    {processing && <Spinner />}
                                    {isEditing ? 'Save changes' : 'Save'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function WorkFields(
    props: Props & {
        work: Work;
        errors: Record<string, string>;
        editing?: { id: number; values: FormValues };
    },
) {
    const error = (name: string) =>
        props.errors[name] && (
            <p className="text-sm text-destructive">{props.errors[name]}</p>
        );
    const value = (name: string, fallback = ''): string | number => {
        const current = props.editing?.values[name];

        return typeof current === 'string' || typeof current === 'number'
            ? current
            : fallback;
    };
    const checked = (name: string, fallback = false): boolean => {
        const current = props.editing?.values[name];

        return typeof current === 'boolean' ? current : fallback;
    };
    const selectedIds = (name: string): string[] => {
        const current = props.editing?.values[name];

        return Array.isArray(current) ? current.map(String) : [];
    };
    const selectUsers = (name: string, valuesName: string) => (
        <Field label="Assign team members">
            <SearchableMultiSelect
                name={name}
                defaultSelectedValues={selectedIds(valuesName)}
                options={props.users.map((user) => ({
                    value: String(user.id),
                    label: `${user.name} · ${user.email}`,
                    searchText: user.email,
                }))}
                placeholder="Choose team members"
            />
        </Field>
    );

    if (props.work === 'tasks') {
        return (
            <>
                <Field label="Task title" error={error('title')}>
                    <Input
                        name="title"
                        defaultValue={value('title')}
                        required
                    />
                </Field>
                <Field label="Description">
                    <textarea
                        name="description"
                        defaultValue={value('description')}
                        className="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                </Field>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Deadline" error={error('due_at')}>
                        <Input
                            name="due_at"
                            type="date"
                            defaultValue={dateInputValue(value('due_at'))}
                        />
                    </Field>
                    <Field label="Priority">
                        <Select
                            name="priority"
                            options={['normal', 'low', 'high', 'urgent']}
                            defaultValue={value(
                                'priority',
                                'normal',
                            ).toString()}
                        />
                    </Field>
                </div>
                {selectUsers('assignee_ids[]', 'assignee_ids')}
            </>
        );
    }

    if (props.work === 'time-entries') {
        return (
            <>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Date" error={error('entry_date')}>
                        <Input
                            name="entry_date"
                            type="date"
                            defaultValue={dateInputValue(value('entry_date'))}
                            required
                        />
                    </Field>
                    <Field label="Hours" error={error('hours')}>
                        <Input
                            name="hours"
                            type="number"
                            min="0.25"
                            step="0.25"
                            defaultValue={value('hours')}
                            required
                        />
                    </Field>
                </div>
                <Field label="Work description" error={error('description')}>
                    <textarea
                        name="description"
                        defaultValue={value('description')}
                        required
                        className="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                </Field>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Hourly rate">
                        <Input
                            name="hourly_rate"
                            type="number"
                            min="0"
                            step="0.01"
                            defaultValue={value(
                                'hourly_rate',
                                props.matter.default_hourly_rate ?? '',
                            )}
                        />
                    </Field>
                    <Field label="Status">
                        <Select
                            name="status"
                            options={['draft', 'submitted', 'approved']}
                            defaultValue={value('status', 'draft').toString()}
                        />
                    </Field>
                </div>
                <CheckField
                    name="is_billable"
                    label="Billable time"
                    defaultChecked={checked('is_billable', true)}
                />
            </>
        );
    }

    if (props.work === 'documents') {
        return (
            <>
                <Field label="File" error={error('document')}>
                    <Input
                        name="document"
                        type="file"
                        required={props.editing === undefined}
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png"
                    />
                </Field>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Display name">
                        <Input name="name" defaultValue={value('name')} />
                    </Field>
                    <Field label="Category">
                        <Input
                            name="category"
                            defaultValue={value('category', 'general')}
                            required
                        />
                    </Field>
                </div>
                <Field label="Visibility">
                    <Select
                        name="visibility"
                        options={['internal', 'client']}
                        defaultValue={value(
                            'visibility',
                            'internal',
                        ).toString()}
                    />
                </Field>
            </>
        );
    }

    if (props.work === 'notes') {
        return (
            <Field label="Internal note" error={error('body')}>
                <textarea
                    name="body"
                    defaultValue={value('body')}
                    required
                    className="min-h-36 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                />
            </Field>
        );
    }

    if (props.work === 'emails') {
        return (
            <>
                <Field label="To" error={error('to')}>
                    <Input
                        name="to"
                        type="text"
                        required
                        placeholder="recipient@example.com, another@example.com"
                    />
                </Field>
                <Field label="Cc">
                    <Input
                        name="cc"
                        type="text"
                        placeholder="Optional comma-separated recipients"
                    />
                </Field>
                <Field label="Subject" error={error('subject')}>
                    <Input name="subject" required />
                </Field>
                <Field label="Message" error={error('body')}>
                    <textarea
                        name="body"
                        required
                        className="min-h-36 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                </Field>
                <Field label="Attach matter documents">
                    <SearchableMultiSelect
                        name="document_ids[]"
                        options={props.matter.documents.map((document) => ({
                            value: String(document.id),
                            label: document.name,
                        }))}
                        placeholder="Choose documents"
                    />
                </Field>
            </>
        );
    }

    if (props.work === 'calendar-events') {
        return (
            <>
                <Field label="Event title" error={error('title')}>
                    <Input
                        name="title"
                        defaultValue={value('title')}
                        required
                    />
                </Field>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Type">
                        <Select
                            name="type"
                            options={[
                                'meeting',
                                'hearing',
                                'deadline',
                                'reminder',
                            ]}
                            defaultValue={value('type', 'meeting').toString()}
                        />
                    </Field>
                    <Field label="Location">
                        <Input
                            name="location"
                            defaultValue={value('location')}
                        />
                    </Field>
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Starts" error={error('starts_at')}>
                        <Input
                            name="starts_at"
                            type="datetime-local"
                            defaultValue={dateTimeInputValue(
                                value('starts_at'),
                            )}
                            required
                        />
                    </Field>
                    <Field label="Ends" error={error('ends_at')}>
                        <Input
                            name="ends_at"
                            type="datetime-local"
                            defaultValue={dateTimeInputValue(value('ends_at'))}
                            required
                        />
                    </Field>
                </div>
                <CheckField
                    name="is_all_day"
                    label="All-day event"
                    defaultChecked={checked('is_all_day')}
                />
                {selectUsers('attendee_ids[]', 'attendee_ids')}
            </>
        );
    }

    if (props.work === 'expenses') {
        return (
            <>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        label="Category"
                        error={error('expense_category_id')}
                    >
                        <SearchableSelect
                            name="expense_category_id"
                            required
                            defaultValue={value(
                                'expense_category_id',
                            ).toString()}
                            options={props.expenseCategories.map(
                                (category) => ({
                                    value: String(category.id),
                                    label: category.name,
                                }),
                            )}
                        />
                    </Field>
                    <Field label="Amount" error={error('amount')}>
                        <Input
                            name="amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            defaultValue={value('amount')}
                            required
                        />
                    </Field>
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Vendor">
                        <Input name="vendor" defaultValue={value('vendor')} />
                    </Field>
                    <Field label="Incurred on">
                        <Input
                            name="incurred_at"
                            type="date"
                            defaultValue={dateInputValue(value('incurred_at'))}
                            required
                        />
                    </Field>
                </div>
                <Field label="Description">
                    <textarea
                        name="description"
                        defaultValue={value('description')}
                        className="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                </Field>
                <Field label="Receipt">
                    <Input
                        name="receipt"
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png"
                    />
                </Field>
                <CheckField
                    name="is_billable"
                    label="Bill this expense to the client"
                    defaultChecked={checked('is_billable')}
                />
            </>
        );
    }

    return (
        <>
            <Field label="Billing item">
                <SearchableSelect
                    name="billing_item_id"
                    defaultValue={value('billing_item_id').toString()}
                    placeholder="Custom flat fee"
                    options={[
                        { value: '', label: 'Custom flat fee' },
                        ...props.billingItems.map((item) => ({
                            value: String(item.id),
                            label: item.name,
                        })),
                    ]}
                />
            </Field>
            <Field label="Description" error={error('description')}>
                <Input
                    name="description"
                    defaultValue={value('description')}
                    required
                />
            </Field>
            <div className="grid gap-4 sm:grid-cols-3">
                <Field label="Quantity">
                    <Input
                        name="quantity"
                        type="number"
                        min="0.01"
                        step="0.01"
                        defaultValue={value('quantity', '1')}
                        required
                    />
                </Field>
                <Field label="Unit price">
                    <Input
                        name="unit_price"
                        type="number"
                        min="0"
                        step="0.01"
                        defaultValue={value('unit_price')}
                        required
                    />
                </Field>
                <Field label="Tax rate %">
                    <Input
                        name="tax_rate"
                        type="number"
                        min="0"
                        step="0.01"
                        defaultValue={value('tax_rate', '0')}
                        required
                    />
                </Field>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Charge date">
                    <Input
                        name="charged_at"
                        type="date"
                        defaultValue={dateInputValue(value('charged_at'))}
                        required
                    />
                </Field>
                <Field label="Status">
                    <Select
                        name="status"
                        options={['draft', 'billable']}
                        defaultValue={value('status', 'draft').toString()}
                    />
                </Field>
            </div>
        </>
    );
}

function Field({
    label,
    children,
    error,
}: {
    label: string;
    children: React.ReactNode;
    error?: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
            {error}
        </div>
    );
}
function Select({
    name,
    options,
    defaultValue,
}: {
    name: string;
    options: string[];
    defaultValue?: string;
}) {
    return (
        <select
            name={name}
            defaultValue={defaultValue}
            className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
        >
            {options.map((option) => (
                <option key={option} value={option}>
                    {option.replace('_', ' ')}
                </option>
            ))}
        </select>
    );
}
function CheckField({
    name,
    label,
    defaultChecked = false,
}: {
    name: string;
    label: string;
    defaultChecked?: boolean;
}) {
    return (
        <label className="flex items-center gap-2 text-sm">
            <input type="hidden" name={name} value="0" />
            <input
                className="size-4 accent-primary"
                type="checkbox"
                name={name}
                value="1"
                defaultChecked={defaultChecked}
            />
            {label}
        </label>
    );
}
function formatDate(value: string): string {
    return new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium' }).format(
        new Date(value),
    );
}
function dateInputValue(value: string | number): string {
    return typeof value === 'string' ? value.slice(0, 10) : '';
}
function dateTimeInputValue(value: string | number): string {
    return typeof value === 'string' ? value.slice(0, 16) : '';
}
function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat('en-GB', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
