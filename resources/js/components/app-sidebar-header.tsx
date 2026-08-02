import { Link, router, useHttp, usePage } from '@inertiajs/react';
import {
    Bell,
    BriefcaseBusiness,
    CheckCheck,
    CircleHelp,
    FileText,
    LoaderCircle,
    Moon,
    ReceiptText,
    Search,
    Sun,
    Timer,
    UsersRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { useTimeTracker } from '@/components/time-tracker';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { SidebarTrigger } from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppearance } from '@/hooks/use-appearance';
import { index as users } from '@/routes/administration/users';
import { index as clients } from '@/routes/clients';
import { index as files } from '@/routes/files';
import { index as help } from '@/routes/help';
import { index as invoices } from '@/routes/invoices';
import { show as matterWorkspace } from '@/routes/matters';
import { index as notifications, read } from '@/routes/notifications';
import {
    index as search,
    suggestions as searchSuggestions,
} from '@/routes/search';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const [searchQuery, setSearchQuery] = useState('');
    const [suggestionsOpen, setSuggestionsOpen] = useState(false);
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const { tracker, setDialogOpen: setTimerDialogOpen } = useTimeTracker();
    const liveSearch = useHttp<Record<string, never>, SearchSuggestionResponse>(
        {},
    );
    const { cancel: cancelLiveSearch, get: getSearchSuggestions } = liveSearch;
    const alerts = usePage().props.notifications ?? {
        unread_count: 0,
        recent: [],
    };

    useEffect(() => {
        const query = searchQuery.trim();

        cancelLiveSearch();

        if (query.length < 2) {
            return;
        }

        const timeout = window.setTimeout(() => {
            void getSearchSuggestions(
                searchSuggestions({ query: { query } }).url,
            );
        }, 180);

        return () => window.clearTimeout(timeout);
    }, [cancelLiveSearch, getSearchSuggestions, searchQuery]);

    const resultGroups = liveSearch.response?.results ?? emptySearchSuggestions;
    const hasSuggestions = Object.values(resultGroups).some(
        (results) => results.length > 0,
    );

    return (
        <header className="flex h-16 shrink-0 items-center gap-3 border-b border-sidebar-border/50 px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-5">
            <div className="flex shrink-0 items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <form
                className="hidden min-w-0 flex-1 lg:block"
                onSubmit={(event: FormEvent<HTMLFormElement>) => {
                    event.preventDefault();
                    router.get(search(), { query: searchQuery.trim() });
                }}
            >
                <div className="relative mx-auto max-w-xl">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={searchQuery}
                        onChange={(event) => setSearchQuery(event.target.value)}
                        onFocus={() => setSuggestionsOpen(true)}
                        onBlur={() => {
                            window.setTimeout(
                                () => setSuggestionsOpen(false),
                                150,
                            );
                        }}
                        placeholder="Search clients, matters, invoices, documents…"
                        aria-label="Search the practice workspace"
                        className="h-9 bg-muted/40 pr-20 pl-9 shadow-none"
                    />
                    <kbd className="pointer-events-none absolute top-1/2 right-3 hidden -translate-y-1/2 rounded border bg-background px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground xl:block">
                        Enter
                    </kbd>
                    {suggestionsOpen && searchQuery.trim().length >= 2 && (
                        <div className="absolute top-[calc(100%+0.5rem)] z-50 w-full overflow-hidden rounded-xl border bg-popover shadow-lg">
                            {liveSearch.processing && !hasSuggestions ? (
                                <div className="flex items-center gap-2 px-4 py-4 text-sm text-muted-foreground">
                                    <LoaderCircle className="size-4 animate-spin" />
                                    Searching the workspace…
                                </div>
                            ) : hasSuggestions ? (
                                <div className="max-h-100 overflow-y-auto py-1.5">
                                    {searchSuggestionGroups.map((group) => {
                                        const results = resultGroups[group.key];

                                        if (results.length === 0) {
                                            return null;
                                        }

                                        const Icon = group.icon;

                                        return (
                                            <div
                                                key={group.key}
                                                className="py-1"
                                            >
                                                <p className="px-3 py-1.5 text-[0.68rem] font-semibold tracking-[0.08em] text-muted-foreground uppercase">
                                                    {group.label}
                                                </p>
                                                {results.map((result) => (
                                                    <Link
                                                        key={result.id}
                                                        href={group.href(
                                                            result,
                                                        )}
                                                        onClick={() =>
                                                            setSuggestionsOpen(
                                                                false,
                                                            )
                                                        }
                                                        className="flex items-center gap-3 px-3 py-2.5 transition-colors hover:bg-muted"
                                                    >
                                                        <span className="grid size-8 shrink-0 place-items-center rounded-md bg-muted text-primary">
                                                            <Icon className="size-4" />
                                                        </span>
                                                        <span className="min-w-0">
                                                            <span className="block truncate text-sm font-medium">
                                                                {result.title}
                                                            </span>
                                                            <span className="block truncate text-xs text-muted-foreground">
                                                                {
                                                                    result.subtitle
                                                                }
                                                            </span>
                                                        </span>
                                                    </Link>
                                                ))}
                                            </div>
                                        );
                                    })}
                                </div>
                            ) : (
                                <p className="px-4 py-5 text-sm text-muted-foreground">
                                    No matching records yet.
                                </p>
                            )}
                            <Link
                                href={search({
                                    query: { query: searchQuery.trim() },
                                })}
                                onClick={() => setSuggestionsOpen(false)}
                                className="flex items-center justify-center gap-2 border-t px-3 py-3 text-sm font-medium text-primary hover:bg-muted"
                            >
                                <Search className="size-4" />
                                View all results
                            </Link>
                        </div>
                    )}
                </div>
            </form>
            <div className="ml-auto flex items-center gap-1">
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="lg:hidden"
                            asChild
                        >
                            <Link
                                href={search()}
                                aria-label="Search the practice workspace"
                            >
                                <Search className="size-5" />
                            </Link>
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>Search</TooltipContent>
                </Tooltip>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="sm:hidden"
                            asChild
                        >
                            <Link href={help()} aria-label="Open help centre">
                                <CircleHelp className="size-5" />
                            </Link>
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>Help centre</TooltipContent>
                </Tooltip>
                <Button
                    variant="ghost"
                    size="sm"
                    className="hidden gap-2 sm:inline-flex"
                    asChild
                >
                    <Link href={help()}>
                        <CircleHelp className="size-4" />
                        Help
                    </Link>
                </Button>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="relative"
                            aria-label="Open matter timer"
                            onClick={() => setTimerDialogOpen(true)}
                        >
                            <Timer className="size-5" />
                            {tracker !== null && (
                                <span className="absolute top-1 right-1 size-2 rounded-full bg-orange-500" />
                            )}
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                        {tracker === null
                            ? 'Start matter timer'
                            : tracker.status === 'stopped'
                              ? 'Save stopped timer'
                              : 'Open matter timer'}
                    </TooltipContent>
                </Tooltip>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={
                                resolvedAppearance === 'dark'
                                    ? 'Use light mode'
                                    : 'Use dark mode'
                            }
                            onClick={() =>
                                updateAppearance(
                                    resolvedAppearance === 'dark'
                                        ? 'light'
                                        : 'dark',
                                )
                            }
                        >
                            {resolvedAppearance === 'dark' ? (
                                <Sun className="size-5" />
                            ) : (
                                <Moon className="size-5" />
                            )}
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                        {resolvedAppearance === 'dark'
                            ? 'Use light mode'
                            : 'Use dark mode'}
                    </TooltipContent>
                </Tooltip>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="relative ml-auto"
                            aria-label="Open notifications"
                        >
                            <Bell className="size-5" />
                            {alerts.unread_count > 0 && (
                                <span className="absolute top-1 right-1 flex size-4 items-center justify-center rounded-full bg-orange-500 text-[10px] font-semibold text-white">
                                    {alerts.unread_count > 9
                                        ? '9+'
                                        : alerts.unread_count}
                                </span>
                            )}
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-90 p-0">
                        <DropdownMenuLabel className="flex items-center justify-between py-3">
                            Notifications
                            <span className="text-xs font-normal text-muted-foreground">
                                {alerts.unread_count} unread
                            </span>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <div className="max-h-90 overflow-y-auto">
                            {alerts.recent.map((alert) => (
                                <Link
                                    key={alert.id}
                                    href={alert.url}
                                    onClick={() => {
                                        if (alert.read_at === null) {
                                            router.patch(
                                                read(alert.id).url,
                                                {},
                                                {
                                                    preserveScroll: true,
                                                },
                                            );
                                        }
                                    }}
                                    className="block px-3 py-3 hover:bg-muted/50"
                                >
                                    <p className="text-sm font-medium">
                                        {alert.title}
                                    </p>
                                    <p className="mt-1 line-clamp-2 text-xs text-muted-foreground">
                                        {alert.body}
                                    </p>
                                </Link>
                            ))}
                            {alerts.recent.length === 0 && (
                                <p className="px-3 py-8 text-center text-sm text-muted-foreground">
                                    You are all caught up.
                                </p>
                            )}
                        </div>
                        <DropdownMenuSeparator />
                        <Link
                            href={notifications()}
                            className="flex items-center justify-center gap-2 px-3 py-3 text-sm font-medium text-primary hover:bg-muted/50"
                        >
                            <CheckCheck className="size-4" />
                            View all notifications
                        </Link>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>
    );
}

type SearchSuggestion = {
    id: number;
    title: string;
    subtitle: string;
};

type SearchSuggestionGroups = Record<
    'clients' | 'matters' | 'invoices' | 'documents' | 'users',
    SearchSuggestion[]
>;

type SearchSuggestionResponse = {
    results: SearchSuggestionGroups;
};

const emptySearchSuggestions: SearchSuggestionGroups = {
    clients: [],
    matters: [],
    invoices: [],
    documents: [],
    users: [],
};

const searchSuggestionGroups: {
    key: keyof SearchSuggestionGroups;
    label: string;
    icon: LucideIcon;
    href: (result: SearchSuggestion) => string;
}[] = [
    {
        key: 'clients',
        label: 'Clients',
        icon: BriefcaseBusiness,
        href: () => clients().url,
    },
    {
        key: 'matters',
        label: 'Matters',
        icon: FileText,
        href: (result) => matterWorkspace(result.id).url,
    },
    {
        key: 'invoices',
        label: 'Invoices',
        icon: ReceiptText,
        href: () => invoices().url,
    },
    {
        key: 'documents',
        label: 'Documents',
        icon: FileText,
        href: () => files().url,
    },
    {
        key: 'users',
        label: 'People',
        icon: UsersRound,
        href: () => users().url,
    },
];
