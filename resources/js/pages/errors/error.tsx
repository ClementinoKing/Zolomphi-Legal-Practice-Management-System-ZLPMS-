import { Link } from '@inertiajs/react';
import { ArrowLeft, House, RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

type ErrorPageProps = {
    status: number;
    reference?: string;
};

type ErrorContent = {
    eyebrow: string;
    title: string;
    description: string;
    badgeClassName: string;
    retry: boolean;
};

const genericError: ErrorContent = {
    eyebrow: 'Unexpected response',
    title: 'Something went wrong',
    description:
        'We could not complete that request. Please try again or return to the application start page.',
    badgeClassName:
        'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200',
    retry: true,
};

const errorContent: Record<number, ErrorContent> = {
    401: {
        eyebrow: 'Authentication required',
        title: 'Please sign in to continue',
        description:
            'Your session is not available for this page. Sign in again and we will get you back to work.',
        badgeClassName:
            'border-blue-200 bg-blue-50 text-primary dark:border-blue-900 dark:bg-blue-950/60 dark:text-blue-200',
        retry: false,
    },
    403: {
        eyebrow: 'Access restricted',
        title: 'You do not have access to this area',
        description:
            'This page is restricted to users with the appropriate permissions. If you think this is a mistake, contact your practice administrator.',
        badgeClassName:
            'border-orange-200 bg-orange-50 text-orange-800 dark:border-orange-900 dark:bg-orange-950/60 dark:text-orange-200',
        retry: false,
    },
    404: {
        eyebrow: 'Page not found',
        title: 'We could not find that page',
        description:
            'The link may be outdated, or the page may have moved. Check the address or return to the application start page.',
        badgeClassName:
            'border-blue-200 bg-blue-50 text-primary dark:border-blue-900 dark:bg-blue-950/60 dark:text-blue-200',
        retry: false,
    },
    419: {
        eyebrow: 'Session expired',
        title: 'Please refresh and try again',
        description:
            'Your secure session expired while you were working. Refresh the page to continue safely.',
        badgeClassName:
            'border-orange-200 bg-orange-50 text-orange-800 dark:border-orange-900 dark:bg-orange-950/60 dark:text-orange-200',
        retry: true,
    },
    429: {
        eyebrow: 'Too many requests',
        title: 'Please wait a moment',
        description:
            'We are protecting the practice workspace from an unusually high number of requests. Try again shortly.',
        badgeClassName:
            'border-orange-200 bg-orange-50 text-orange-800 dark:border-orange-900 dark:bg-orange-950/60 dark:text-orange-200',
        retry: true,
    },
    500: {
        eyebrow: 'Server error',
        title: 'We hit an unexpected problem',
        description:
            'The request could not be completed on our side. Try again, and contact your administrator if the problem continues.',
        badgeClassName:
            'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/60 dark:text-red-200',
        retry: true,
    },
    503: {
        eyebrow: 'Temporarily unavailable',
        title: 'We will be back shortly',
        description:
            'The practice workspace is temporarily unavailable while we complete maintenance or recover a service.',
        badgeClassName:
            'border-blue-200 bg-blue-50 text-primary dark:border-blue-900 dark:bg-blue-950/60 dark:text-blue-200',
        retry: true,
    },
};

export default function ErrorPage({ status, reference }: ErrorPageProps) {
    const content = errorContent[status] ?? genericError;

    const goBack = (): void => {
        if (window.history.length > 1) {
            window.history.back();

            return;
        }

        window.location.assign(home().url);
    };

    return (
        <main className="relative flex min-h-svh overflow-hidden bg-[#f7f8fc] text-foreground dark:bg-[#111426]">
            <div
                aria-hidden="true"
                className="pointer-events-none absolute -top-32 -right-24 size-96 rounded-full bg-primary/10 blur-3xl dark:bg-primary/20"
            />
            <div
                aria-hidden="true"
                className="pointer-events-none absolute -bottom-40 -left-24 size-[28rem] rounded-full bg-orange-500/10 blur-3xl dark:bg-orange-500/10"
            />
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 [background-image:linear-gradient(to_right,rgba(25,30,94,0.06)_1px,transparent_1px),linear-gradient(to_bottom,rgba(25,30,94,0.06)_1px,transparent_1px)] [background-size:56px_56px] opacity-40 dark:[background-image:linear-gradient(to_right,rgba(255,255,255,0.06)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.06)_1px,transparent_1px)] dark:opacity-20"
            />

            <div className="relative mx-auto flex min-h-svh w-full max-w-3xl flex-col px-5 py-8 sm:px-8 sm:py-10">
                <section className="flex flex-1 items-center justify-center py-14 sm:py-20">
                    <div className="w-full max-w-xl text-center">
                        <div
                            className={`mx-auto inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold tracking-wide uppercase ${content.badgeClassName}`}
                        >
                            {content.eyebrow} · {status}
                        </div>
                        <h1 className="mt-5 text-3xl font-semibold tracking-tight text-primary sm:text-5xl dark:text-blue-100">
                            {content.title}
                        </h1>
                        <p className="mx-auto mt-5 max-w-lg text-base leading-7 text-muted-foreground sm:text-lg">
                            {content.description}
                        </p>

                        <div className="mt-9 flex flex-col justify-center gap-3 sm:flex-row">
                            <Button
                                type="button"
                                variant="default"
                                size="lg"
                                onClick={goBack}
                            >
                                <ArrowLeft className="size-4" />
                                Go back
                            </Button>
                            {content.retry && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="lg"
                                    onClick={() => window.location.reload()}
                                >
                                    <RefreshCw className="size-4" />
                                    Try again
                                </Button>
                            )}
                            {!content.retry && (
                                <Button asChild variant="outline" size="lg">
                                    <Link href={home()}>
                                        <House className="size-4" />
                                        Go to start
                                    </Link>
                                </Button>
                            )}
                        </div>

                        {reference && (
                            <p className="mt-8 text-xs text-muted-foreground">
                                Reference ID:{' '}
                                <span className="font-mono font-medium text-foreground/70">
                                    {reference}
                                </span>
                            </p>
                        )}
                    </div>
                </section>

                <footer className="flex items-center justify-between gap-4 border-t border-primary/10 pt-5 text-xs text-muted-foreground dark:border-white/10">
                    <span>Secure practice workspace</span>
                    <button
                        type="button"
                        onClick={goBack}
                        className="rounded-md px-2 py-1 font-medium text-primary underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none dark:text-blue-200"
                    >
                        Return to previous page
                    </button>
                </footer>
            </div>
        </main>
    );
}
