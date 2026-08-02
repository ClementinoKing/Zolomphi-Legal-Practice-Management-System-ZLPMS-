import { Head } from '@inertiajs/react';
import {
    Bell,
    BriefcaseBusiness,
    CalendarDays,
    CheckCircle2,
    CircleHelp,
    Clock3,
    FileText,
    FolderOpen,
    ReceiptText,
    Search,
    Settings2,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import type { BreadcrumbItem } from '@/types';

type HelpStep = {
    title: string;
    description: string;
    icon: LucideIcon;
};

const gettingStartedSteps: HelpStep[] = [
    {
        title: 'Set up the firm workspace',
        description:
            'Add firm details, departments, positions, roles, permissions, and billing reference data before inviting the wider team.',
        icon: Settings2,
    },
    {
        title: 'Create a client',
        description:
            'Add the individual or organisation your firm represents. Keep their key contact details and client-specific information together in one record.',
        icon: BriefcaseBusiness,
    },
    {
        title: 'Open a matter',
        description:
            'Create the legal matter under its client, select the matter type, responsible attorney, participants, billing arrangement, budget, and deadline.',
        icon: FileText,
    },
    {
        title: 'Perform and track legal work',
        description:
            'Use the matter workspace to manage tasks, deadlines, documents, notes, emails, calendar events, time entries, expenses, and flat fees.',
        icon: CheckCircle2,
    },
    {
        title: 'Build invoices and record payments',
        description:
            'Create invoice drafts from approved billable work, then record payments or create a staged payment plan against the outstanding balance.',
        icon: Wallet,
    },
];

const workspaceGuides: HelpStep[] = [
    {
        title: 'Track time accurately',
        description:
            'Use the timer in the header to start work against a matter. Pause it for breaks, stop it when finished, then describe the completed activities before saving the time entry.',
        icon: Clock3,
    },
    {
        title: 'Keep deadlines visible',
        description:
            'Review the Calendar for matter deadlines and appointments. Select an item to open its matter and keep tasks, due dates, and events up to date.',
        icon: CalendarDays,
    },
    {
        title: 'Organise firm documents',
        description:
            'Use Company Drive for organisation and client folders. Client documents should stay in the relevant client folder so the team can find them quickly.',
        icon: FolderOpen,
    },
    {
        title: 'Monitor billing',
        description:
            'Use Invoices to review draft and outstanding invoices. Payments show what has been collected, what remains due, and any active payment-plan installments.',
        icon: ReceiptText,
    },
];

export default function HelpIndex() {
    return (
        <>
            <Head title="Help centre" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-8 p-4 md:p-6">
                <header className="max-w-3xl space-y-3">
                    <div className="grid size-11 place-items-center rounded-xl bg-primary text-primary-foreground">
                        <CircleHelp className="size-6" />
                    </div>
                    <div className="space-y-2">
                        <p className="text-sm font-medium text-primary">
                            Help centre
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            How to use Zolomphi
                        </h1>
                        <p className="text-sm leading-6 text-muted-foreground">
                            Follow the workflow below to take a client from
                            onboarding through legal work, billing, and payment.
                        </p>
                    </div>
                </header>

                <section className="space-y-4">
                    <div>
                        <h2 className="text-lg font-semibold">
                            Getting started
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Use this sequence when setting up and running a new
                            client engagement.
                        </p>
                    </div>
                    <div className="grid gap-4 lg:grid-cols-5">
                        {gettingStartedSteps.map((step, index) => {
                            const Icon = step.icon;

                            return (
                                <Card key={step.title} className="h-full">
                                    <CardContent className="space-y-4 p-5">
                                        <div className="flex items-center justify-between">
                                            <span className="grid size-9 place-items-center rounded-lg bg-primary/10 text-primary">
                                                <Icon className="size-4" />
                                            </span>
                                            <span className="text-sm font-semibold text-muted-foreground">
                                                {String(index + 1).padStart(
                                                    2,
                                                    '0',
                                                )}
                                            </span>
                                        </div>
                                        <div className="space-y-1.5">
                                            <h3 className="font-semibold">
                                                {step.title}
                                            </h3>
                                            <p className="text-sm leading-6 text-muted-foreground">
                                                {step.description}
                                            </p>
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                </section>

                <section className="grid gap-4 lg:grid-cols-2">
                    {workspaceGuides.map((guide) => {
                        const Icon = guide.icon;

                        return (
                            <Card key={guide.title}>
                                <CardContent className="flex gap-4 p-5">
                                    <span className="grid size-10 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
                                        <Icon className="size-5" />
                                    </span>
                                    <div className="space-y-1.5">
                                        <h2 className="font-semibold">
                                            {guide.title}
                                        </h2>
                                        <p className="text-sm leading-6 text-muted-foreground">
                                            {guide.description}
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </section>

                <Card className="border-primary/15 bg-primary/5">
                    <CardContent className="grid gap-5 p-5 sm:grid-cols-2">
                        <div className="flex gap-3">
                            <Search className="mt-0.5 size-5 shrink-0 text-primary" />
                            <div>
                                <h2 className="font-semibold">
                                    Find records quickly
                                </h2>
                                <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                    Use the search bar in the header to look up
                                    clients, matters, invoices, and other
                                    practice records without leaving your
                                    current page.
                                </p>
                            </div>
                        </div>
                        <div className="flex gap-3">
                            <Bell className="mt-0.5 size-5 shrink-0 text-primary" />
                            <div>
                                <h2 className="font-semibold">
                                    Stay on top of work
                                </h2>
                                <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                    Check notifications for new assignments,
                                    documents, invoice activity, and payments.
                                    Use the dashboard, reports, and audit logs
                                    to monitor the firm as a whole.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

HelpIndex.layout = {
    breadcrumbs: [
        { title: 'Help centre', href: '/help' },
    ] satisfies BreadcrumbItem[],
};
