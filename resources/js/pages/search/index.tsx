import { Head, Link, router } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    FileText,
    ReceiptText,
    Search,
    SearchX,
    UsersRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { index as users } from '@/routes/administration/users';
import { index as clients } from '@/routes/clients';
import { index as files } from '@/routes/files';
import { index as invoices } from '@/routes/invoices';
import { show as matterWorkspace } from '@/routes/matters';
import { index as search } from '@/routes/search';
import type { BreadcrumbItem } from '@/types';

type SearchResult = {
    id: number;
    title: string;
    subtitle: string;
};

type Props = {
    query: string;
    results: Partial<
        Record<
            'clients' | 'matters' | 'invoices' | 'documents' | 'users',
            SearchResult[]
        >
    >;
};

const sections: {
    key: keyof Props['results'];
    title: string;
    icon: LucideIcon;
    href: (result: SearchResult) => string;
}[] = [
    {
        key: 'clients',
        title: 'Clients',
        icon: BriefcaseBusiness,
        href: () => clients().url,
    },
    {
        key: 'matters',
        title: 'Matters',
        icon: FileText,
        href: (result) => matterWorkspace(result.id).url,
    },
    {
        key: 'invoices',
        title: 'Invoices',
        icon: ReceiptText,
        href: () => invoices().url,
    },
    {
        key: 'documents',
        title: 'Documents',
        icon: FileText,
        href: () => files().url,
    },
    {
        key: 'users',
        title: 'People',
        icon: UsersRound,
        href: () => users().url,
    },
];

export default function SearchIndex({ query, results }: Props) {
    const [searchQuery, setSearchQuery] = useState(query);
    const total = Object.values(results).reduce(
        (count, records) => count + (records?.length ?? 0),
        0,
    );

    return (
        <>
            <Head title={query === '' ? 'Search' : `Search: ${query}`} />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-8 p-4 md:p-6">
                <header className="max-w-3xl space-y-2">
                    <p className="text-sm font-medium text-primary">
                        Global search
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Search the practice workspace
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Find clients, matters, invoices, documents, and team
                        members from one place.
                    </p>
                </header>

                <form
                    className="max-w-3xl"
                    onSubmit={(event: FormEvent<HTMLFormElement>) => {
                        event.preventDefault();
                        router.get(search(), { query: searchQuery.trim() });
                    }}
                >
                    <div className="flex gap-2">
                        <div className="relative min-w-0 flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={searchQuery}
                                onChange={(event) =>
                                    setSearchQuery(event.target.value)
                                }
                                placeholder="Search clients, matters, invoices, documents…"
                                aria-label="Search the practice workspace"
                                className="h-10 pl-9"
                                autoFocus
                            />
                        </div>
                        <Button type="submit">
                            <Search />
                            Search
                        </Button>
                    </div>
                </form>

                {query === '' ? (
                    <EmptySearchState
                        title="Start with the search bar above"
                        description="Enter a client name, matter number, invoice number, document name, or colleague."
                    />
                ) : total === 0 ? (
                    <EmptySearchState
                        title={`No results for “${query}”`}
                        description="Try a different name, number, or keyword."
                    />
                ) : (
                    <div className="grid gap-5 lg:grid-cols-2">
                        {sections.map((section) => {
                            const records = results[section.key] ?? [];

                            if (records.length === 0) {
                                return null;
                            }

                            const Icon = section.icon;

                            return (
                                <Card key={section.key} className="shadow-none">
                                    <CardContent className="p-0">
                                        <div className="flex items-center gap-2 border-b px-5 py-4">
                                            <Icon className="size-4 text-primary" />
                                            <h2 className="font-semibold">
                                                {section.title}
                                            </h2>
                                            <span className="ml-auto text-xs text-muted-foreground">
                                                {records.length} result
                                                {records.length === 1
                                                    ? ''
                                                    : 's'}
                                            </span>
                                        </div>
                                        <div className="divide-y">
                                            {records.map((result) => (
                                                <Link
                                                    key={result.id}
                                                    href={section.href(result)}
                                                    className="block px-5 py-4 transition-colors hover:bg-muted/50"
                                                >
                                                    <p className="font-medium">
                                                        {result.title}
                                                    </p>
                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                        {result.subtitle}
                                                    </p>
                                                </Link>
                                            ))}
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </div>
        </>
    );
}

function EmptySearchState({
    title,
    description,
}: {
    title: string;
    description: string;
}) {
    return (
        <div className="grid min-h-72 place-items-center rounded-xl border border-dashed bg-muted/20 p-8 text-center">
            <div className="max-w-sm space-y-3">
                <SearchX className="mx-auto size-7 text-muted-foreground" />
                <h2 className="font-semibold">{title}</h2>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
        </div>
    );
}

SearchIndex.layout = {
    breadcrumbs: [
        { title: 'Search', href: '/search' },
    ] satisfies BreadcrumbItem[],
};
