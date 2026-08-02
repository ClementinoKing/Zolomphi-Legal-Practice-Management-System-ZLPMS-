import { Form, Head, Link, router } from '@inertiajs/react';
import {
    CalendarClock,
    Check,
    ClipboardList,
    Pencil,
    Plus,
    Search,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { complete, index as internalTasks, store, update } from '@/routes/internal-tasks';

type User = { id: number; name: string; email: string };
type Task = {
    id: number;
    title: string;
    description: string | null;
    status: 'pending' | 'in_progress' | 'completed' | 'cancelled';
    priority: 'low' | 'normal' | 'high' | 'urgent';
    due_at: string | null;
    completed_at: string | null;
    creator: { id: number; name: string } | null;
    assignees: Array<{ id: number; name: string }>;
    can_edit: boolean;
    can_complete: boolean;
};
type Paginated<T> = {
    data: T[];
    from: number | null;
    to: number | null;
    total: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
type Props = {
    tasks: Paginated<Task>;
    users: User[];
    openTaskId: number | null;
    filters: { view: 'assigned' | 'created' | 'all'; status: string; search: string };
    stats: { open: number; due_soon: number; overdue: number; completed: number };
};

const statusLabels: Record<Task['status'], string> = {
    pending: 'Pending',
    in_progress: 'In progress',
    completed: 'Completed',
    cancelled: 'Cancelled',
};
const priorityLabels: Record<Task['priority'], string> = {
    low: 'Low',
    normal: 'Normal',
    high: 'High',
    urgent: 'Urgent',
};

export default function InternalTasks({ tasks, users, openTaskId, filters, stats }: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingTask, setEditingTask] = useState<Task | null>(null);

    const openCreate = () => {
        setEditingTask(null);
        setDialogOpen(true);
    };
    const openEdit = (task: Task) => {
        setEditingTask(task);
        setDialogOpen(true);
    };

    return (
        <>
            <Head title="Internal tasks" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <p className="text-sm font-medium text-primary">Team workspace</p>
                        <h1 className="text-2xl font-semibold tracking-tight">Internal tasks</h1>
                        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                            Assign follow-ups to colleagues, keep due dates visible, and close the loop from one shared task list.
                        </p>
                    </div>
                    <Button onClick={openCreate}><Plus /> Assign a task</Button>
                </header>

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard label="Open tasks" value={stats.open} detail="Assigned or created by you" />
                    <StatCard label="Due this week" value={stats.due_soon} detail="Keep these moving" />
                    <StatCard label="Overdue" value={stats.overdue} detail="Need attention" tone="text-destructive" />
                    <StatCard label="Completed" value={stats.completed} detail="Across your task view" />
                </section>

                <Card className="gap-0 overflow-hidden py-0">
                    <div className="flex flex-col gap-3 border-b p-4 lg:flex-row lg:items-center lg:justify-between">
                        <div className="flex gap-1 rounded-lg bg-muted p-1">
                            {(['assigned', 'created', 'all'] as const).map((view) => (
                                <Link
                                    key={view}
                                    href={internalTasks({ query: { view, status: filters.status || null, search: filters.search || null } }).url}
                                    className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${filters.view === view ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}
                                >
                                    {view === 'assigned' ? 'Assigned to me' : view === 'created' ? 'Assigned by me' : 'All visible'}
                                </Link>
                            ))}
                        </div>
                        <Form action={internalTasks().url} method="get" className="flex gap-2">
                            <div className="relative flex-1 lg:w-64">
                                <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                                <Input name="search" defaultValue={filters.search} placeholder="Search tasks" className="pl-9" />
                            </div>
                            <select name="status" defaultValue={filters.status} className="h-9 rounded-md border border-input bg-background px-3 text-sm">
                                <option value="">Every status</option>
                                {Object.entries(statusLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                            </select>
                            <Button type="submit" variant="outline">Filter</Button>
                        </Form>
                    </div>

                    <CardContent className="p-0">
                        {tasks.data.length === 0 ? (
                            <div className="flex flex-col items-center px-6 py-16 text-center">
                                <span className="grid size-12 place-items-center rounded-full bg-primary/10 text-primary"><ClipboardList className="size-6" /></span>
                                <h2 className="mt-4 font-medium">No internal tasks yet</h2>
                                <p className="mt-1 max-w-sm text-sm text-muted-foreground">Create an assignment for a colleague and it will also appear in their notification inbox.</p>
                                <Button className="mt-5" onClick={openCreate}><Plus /> Assign the first task</Button>
                            </div>
                        ) : (
                            <div className="divide-y">
                                {tasks.data.map((task) => (
                                    <TaskRow key={task.id} task={task} highlighted={task.id === openTaskId} onEdit={openEdit} />
                                ))}
                            </div>
                        )}
                    </CardContent>

                    {tasks.last_page > 1 && <div className="flex items-center justify-between border-t p-4 text-sm text-muted-foreground">
                        <span>Showing {tasks.from}–{tasks.to} of {tasks.total}</span>
                        <div className="flex gap-2">
                            {tasks.prev_page_url && <Button variant="outline" size="sm" onClick={() => router.visit(tasks.prev_page_url!)}>Previous</Button>}
                            {tasks.next_page_url && <Button variant="outline" size="sm" onClick={() => router.visit(tasks.next_page_url!)}>Next</Button>}
                        </div>
                    </div>}
                </Card>
            </div>
            <TaskDialog open={dialogOpen} onOpenChange={setDialogOpen} task={editingTask} users={users} />
        </>
    );
}

function TaskRow({ task, highlighted, onEdit }: { task: Task; highlighted: boolean; onEdit: (task: Task) => void }) {
    const overdue = task.due_at !== null && task.completed_at === null && new Date(task.due_at) < new Date();

    return <div className={`flex flex-col gap-4 p-5 transition-colors lg:flex-row lg:items-center lg:justify-between ${highlighted ? 'bg-primary/5 ring-1 ring-inset ring-primary/20' : 'hover:bg-muted/30'}`}>
        <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
                <h2 className="font-medium">{task.title}</h2>
                <Badge variant="outline" className={priorityClass(task.priority)}>{priorityLabels[task.priority]}</Badge>
                <Badge variant="outline">{statusLabels[task.status]}</Badge>
            </div>
            {task.description && <p className="mt-1 line-clamp-2 max-w-3xl text-sm text-muted-foreground">{task.description}</p>}
            <div className="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-muted-foreground">
                <span className={overdue ? 'font-medium text-destructive' : ''}><CalendarClock className="mr-1 inline size-3.5" />{task.due_at ? (overdue ? `Overdue · ${formatDate(task.due_at)}` : formatDate(task.due_at)) : 'No due date'}</span>
                <span><UserRound className="mr-1 inline size-3.5" />{task.assignees.map((assignee) => assignee.name).join(', ')}</span>
                <span>Created by {task.creator?.name ?? 'former user'}</span>
            </div>
        </div>
        <div className="flex shrink-0 gap-2">
            {task.can_edit && <Button variant="outline" size="sm" onClick={() => onEdit(task)}><Pencil /> Edit</Button>}
            {task.can_complete && <Button size="sm" onClick={() => router.patch(complete(task.id).url, {}, { preserveScroll: true })}><Check /><span className="hidden sm:inline">Complete</span></Button>}
        </div>
    </div>;
}

function TaskDialog({ open, onOpenChange, task, users }: { open: boolean; onOpenChange: (open: boolean) => void; task: Task | null; users: User[] }) {
    const isEditing = task !== null;
    const action = isEditing ? update(task.id).url : store().url;
    const selectedAssignees = task?.assignees.map((assignee) => String(assignee.id)) ?? [];

    return <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-xl">
            <DialogHeader>
                <div className="mb-1 grid size-10 place-items-center rounded-xl bg-primary/10 text-primary"><ClipboardList className="size-5" /></div>
                <DialogTitle>{isEditing ? 'Edit internal task' : 'Assign an internal task'}</DialogTitle>
                <DialogDescription>{isEditing ? 'Update the brief, due date, priority, or team assignments.' : 'Give your colleague enough context to complete the work confidently.'}</DialogDescription>
            </DialogHeader>
            <Form action={action} method={isEditing ? 'patch' : 'post'} onSuccess={() => onOpenChange(false)} className="grid gap-5">
                {({ errors, processing }) => <>
                    <div className="grid gap-2"><Label htmlFor="internal-task-title">Task title</Label><Input id="internal-task-title" name="title" defaultValue={task?.title ?? ''} placeholder="e.g. Prepare the client welcome pack" required /><InputError message={errors.title} /></div>
                    <div className="grid gap-2"><Label htmlFor="internal-task-description">Context and expected outcome</Label><textarea id="internal-task-description" name="description" defaultValue={task?.description ?? ''} placeholder="Add the important context, links, or hand-off details." className="min-h-28 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring" /><InputError message={errors.description} /></div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="grid gap-2 text-sm font-medium">Priority<select name="priority" defaultValue={task?.priority ?? 'normal'} className="h-9 rounded-md border border-input bg-background px-3 text-sm"><option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option></select><InputError message={errors.priority} /></label>
                        <label className="grid gap-2 text-sm font-medium">Due date<Input name="due_at" type="date" defaultValue={task?.due_at ?? ''} /><InputError message={errors.due_at} /></label>
                    </div>
                    {isEditing && <label className="grid gap-2 text-sm font-medium">Status<select name="status" defaultValue={task.status} className="h-9 rounded-md border border-input bg-background px-3 text-sm">{Object.entries(statusLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select><InputError message={errors.status} /></label>}
                    <div className="grid gap-2"><Label htmlFor="internal-task-assignees">Assign to colleagues</Label><select id="internal-task-assignees" name="assignee_ids[]" multiple defaultValue={selectedAssignees} required={!isEditing} className="min-h-32 rounded-md border border-input bg-background px-3 py-2 text-sm">{users.map((user) => <option key={user.id} value={user.id}>{user.name} · {user.email}</option>)}</select><p className="text-xs text-muted-foreground">Hold Command/Ctrl to select more than one colleague.</p><InputError message={errors.assignee_ids} /></div>
                    <DialogFooter><Button type="button" variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button><Button type="submit" disabled={processing}>{processing ? 'Saving…' : isEditing ? 'Save changes' : 'Assign task'}</Button></DialogFooter>
                </>}
            </Form>
        </DialogContent>
    </Dialog>;
}

function StatCard({ label, value, detail, tone = 'text-foreground' }: { label: string; value: number; detail: string; tone?: string }) {
    return <div className="rounded-xl border bg-background p-4"><p className="text-sm text-muted-foreground">{label}</p><p className={`mt-2 text-2xl font-semibold ${tone}`}>{value}</p><p className="mt-1 text-xs text-muted-foreground">{detail}</p></div>;
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(`${value}T00:00:00`));
}

function priorityClass(priority: Task['priority']): string {
    return { low: 'text-muted-foreground', normal: 'text-primary', high: 'border-amber-300 bg-amber-50 text-amber-700', urgent: 'border-red-300 bg-red-50 text-red-700' }[priority];
}

InternalTasks.layout = { breadcrumbs: [{ title: 'Internal tasks', href: internalTasks() }] };
