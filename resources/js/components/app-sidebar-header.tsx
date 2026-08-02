import { Link, router, usePage } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { index as notifications, read } from '@/routes/notifications';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const alerts = usePage().props.notifications ?? {
        unread_count: 0,
        recent: [],
    };

    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
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
        </header>
    );
}
