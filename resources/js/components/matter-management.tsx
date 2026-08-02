import { Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import OpenMatterForm from '@/components/open-matter-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useCurrency } from '@/hooks/use-currency';

type Matter = {
    id: number;
    matter_number: string;
    title: string;
    client: string;
    matter_type: string;
    responsible_user: string;
    billing_type: string;
    budget_amount: string | null;
    status: string;
    priority: string;
    opened_at: string;
    deadline_at: string | null;
    participants_count: number;
    attorneys_count: number;
};

type Props = {
    matters: Matter[];
    clients: Parameters<typeof OpenMatterForm>[0]['clients'];
    responsibleUsers: Parameters<typeof OpenMatterForm>[0]['responsibleUsers'];
    matterTypes: Parameters<typeof OpenMatterForm>[0]['matterTypes'];
    today: string;
};

const pageSize = 10;

export default function MatterManagement({
    matters,
    clients,
    responsibleUsers,
    matterTypes,
    today,
}: Props) {
    const { formatMoney } = useCurrency();
    const [isOpen, setIsOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('all');
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('desc');
    const [page, setPage] = useState(1);

    const filteredMatters = useMemo(() => {
        const term = search.trim().toLocaleLowerCase();

        return matters
            .filter((matter) => {
                const matchesSearch =
                    term === '' ||
                    [
                        matter.matter_number,
                        matter.title,
                        matter.client,
                        matter.matter_type,
                        matter.responsible_user,
                    ].some((value) => value.toLocaleLowerCase().includes(term));

                return (
                    matchesSearch &&
                    (status === 'all' || matter.status === status)
                );
            })
            .sort((first, second) => {
                const comparison = first.opened_at.localeCompare(
                    second.opened_at,
                );

                return sortDirection === 'asc' ? comparison : -comparison;
            });
    }, [matters, search, sortDirection, status]);

    const totalPages = Math.max(
        1,
        Math.ceil(filteredMatters.length / pageSize),
    );
    const currentPage = Math.min(page, totalPages);
    const visibleMatters = filteredMatters.slice(
        (currentPage - 1) * pageSize,
        currentPage * pageSize,
    );

    function formatBudget(value: string | null): string {
        if (value === null) {
            return '—';
        }

        return formatMoney(value, 'MWK', 0);
    }

    return (
        <section className="grid gap-5">
            <header className="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-start sm:justify-between">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="grid gap-1">
                        <p className="text-sm text-muted-foreground">
                            {filteredMatters.length} of {matters.length} matter
                            {matters.length === 1 ? '' : 's'} in the practice
                            workspace.
                        </p>
                    </div>
                </div>
                <Button onClick={() => setIsOpen(true)}>Open matter</Button>
            </header>
            <div className="grid gap-3 rounded-xl bg-muted/35 p-3 md:grid-cols-[minmax(0,1fr)_10rem]">
                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        aria-label="Search matters"
                        className="bg-background pl-9"
                        value={search}
                        placeholder="Search matter, client, type, or attorney"
                        onChange={(event) => {
                            setSearch(event.target.value);
                            setPage(1);
                        }}
                    />
                </div>
                <select
                    aria-label="Filter matters by status"
                    className="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs"
                    value={status}
                    onChange={(event) => {
                        setStatus(event.target.value);
                        setPage(1);
                    }}
                >
                    <option value="all">All statuses</option>
                    <option value="open">Open</option>
                    <option value="pending">Pending</option>
                    <option value="on_hold">On hold</option>
                </select>
            </div>
            <div className="grid gap-4">
                <div className="overflow-x-auto rounded-xl border border-border/70 bg-background">
                    <table className="w-full min-w-350 text-left text-sm">
                        <thead className="bg-muted/50 text-xs tracking-wide text-muted-foreground uppercase">
                            <tr>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Matter
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Client
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Type / attorney
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Billing / budget
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Attorney team
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    <button
                                        type="button"
                                        className="inline-flex items-center gap-1 hover:text-foreground"
                                        onClick={() =>
                                            setSortDirection((current) =>
                                                current === 'asc'
                                                    ? 'desc'
                                                    : 'asc',
                                            )
                                        }
                                    >
                                        Opened{' '}
                                        {sortDirection === 'asc' ? '↑' : '↓'}
                                    </button>
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Deadline
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Status
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 text-right font-medium"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {visibleMatters.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={9}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        No matters match the current filters.
                                    </td>
                                </tr>
                            ) : (
                                visibleMatters.map((matter) => (
                                    <tr
                                        key={matter.id}
                                        className="transition-colors hover:bg-muted/30"
                                    >
                                        <td className="px-4 py-3 align-top">
                                            <p className="font-medium">
                                                {matter.title}
                                            </p>
                                            <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                                                {matter.matter_number}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3 align-top text-muted-foreground">
                                            {matter.client}
                                        </td>
                                        <td className="px-4 py-3 align-top">
                                            <p>{matter.matter_type}</p>
                                            <p className="mt-0.5 text-muted-foreground">
                                                {matter.responsible_user}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3 align-top">
                                            <p className="capitalize">
                                                {matter.billing_type.replace(
                                                    '_',
                                                    ' ',
                                                )}
                                            </p>
                                            <p className="mt-0.5 text-muted-foreground">
                                                {formatBudget(
                                                    matter.budget_amount,
                                                )}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3 align-top text-muted-foreground">
                                            <p>
                                                {matter.attorneys_count}{' '}
                                                attorney
                                                {matter.attorneys_count === 1
                                                    ? ''
                                                    : 's'}
                                            </p>
                                            <p className="mt-0.5 text-xs">
                                                {matter.participants_count}{' '}
                                                external
                                            </p>
                                        </td>
                                        <td className="px-4 py-3 align-top text-muted-foreground">
                                            {matter.opened_at}
                                        </td>
                                        <td className="px-4 py-3 align-top text-muted-foreground">
                                            {matter.deadline_at ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 align-top">
                                            <Badge
                                                variant="outline"
                                                className={matterStatusClassName(
                                                    matter.status,
                                                )}
                                            >
                                                {matter.status.replace(
                                                    '_',
                                                    ' ',
                                                )}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-right align-top">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                asChild
                                            >
                                                <Link
                                                    href={`/matters/${matter.id}`}
                                                >
                                                    Open workspace
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="mt-4 flex flex-col gap-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                    <p>
                        {filteredMatters.length === 0
                            ? 'No matters found'
                            : `Showing ${(currentPage - 1) * pageSize + 1}–${Math.min(currentPage * pageSize, filteredMatters.length)} of ${filteredMatters.length} matters`}
                    </p>
                    <div className="flex items-center gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={currentPage === 1}
                            onClick={() => setPage((current) => current - 1)}
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
                            onClick={() => setPage((current) => current + 1)}
                        >
                            Next
                        </Button>
                    </div>
                </div>
            </div>
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="max-h-[calc(100vh-2rem)] w-[calc(100%-2rem)] overflow-x-hidden overflow-y-auto sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Open matter</DialogTitle>
                        <DialogDescription>
                            Complete the three steps to open a new legal matter.
                        </DialogDescription>
                    </DialogHeader>
                    <OpenMatterForm
                        clients={clients}
                        responsibleUsers={responsibleUsers}
                        matterTypes={matterTypes}
                        today={today}
                        onSuccess={() => setIsOpen(false)}
                    />
                </DialogContent>
            </Dialog>
        </section>
    );
}

function matterStatusClassName(status: string): string {
    const classes: Record<string, string> = {
        open: 'border-emerald-200 bg-emerald-100 text-emerald-800 dark:border-emerald-900/70 dark:bg-emerald-950/50 dark:text-emerald-300',
        pending:
            'border-amber-200 bg-amber-100 text-amber-800 dark:border-amber-900/70 dark:bg-amber-950/50 dark:text-amber-300',
        on_hold:
            'border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200',
        closed: 'border-blue-200 bg-blue-100 text-blue-800 dark:border-blue-900/70 dark:bg-blue-950/50 dark:text-blue-300',
        cancelled:
            'border-red-200 bg-red-100 text-red-800 dark:border-red-900/70 dark:bg-red-950/50 dark:text-red-300',
    };

    return classes[status] ?? classes.pending;
}
