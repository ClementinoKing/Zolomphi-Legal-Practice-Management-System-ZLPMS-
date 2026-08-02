import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

export function NavMain({
    label,
    items = [],
}: {
    label: string;
    items: NavItem[];
}) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-3 py-1.5 group-data-[collapsible=icon]:px-2">
            <SidebarGroupLabel className="h-7 px-2.5 text-[0.68rem] font-semibold tracking-[0.08em] text-sidebar-foreground/60 uppercase">
                {label}
            </SidebarGroupLabel>
            <SidebarMenu className="gap-0.5">
                {items.map((item) => (
                    <NavigationItem
                        key={item.title}
                        item={item}
                        isCurrentUrl={isCurrentUrl}
                    />
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}

function NavigationItem({
    item,
    isCurrentUrl,
}: {
    item: NavItem;
    isCurrentUrl: (href: NavItem['href']) => boolean;
}) {
    const hasChildren = (item.children?.length ?? 0) > 0;
    const hasActiveChild = item.children?.some((child) =>
        isCurrentUrl(child.href),
    );
    const [open, setOpen] = useState(Boolean(hasActiveChild));

    if (!hasChildren) {
        return (
            <SidebarMenuItem>
                <SidebarMenuButton
                    asChild
                    isActive={isCurrentUrl(item.href)}
                    tooltip={{ children: item.title }}
                    className="h-9 rounded-lg px-2.5 text-[0.875rem]"
                >
                    <Link href={item.href} prefetch>
                        {item.icon && <item.icon />}
                        <span>{item.title}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        );
    }

    return (
        <Collapsible open={open} onOpenChange={setOpen} asChild>
            <SidebarMenuItem>
                <CollapsibleTrigger asChild>
                    <SidebarMenuButton
                        isActive={isCurrentUrl(item.href) || hasActiveChild}
                        tooltip={{ children: item.title }}
                        className="h-9 rounded-lg px-2.5 text-[0.875rem]"
                    >
                        {item.icon && <item.icon />}
                        <span>{item.title}</span>
                        <ChevronDown className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-180" />
                    </SidebarMenuButton>
                </CollapsibleTrigger>
                <CollapsibleContent className="overflow-hidden">
                    <SidebarMenuSub className="mt-1 ml-5 gap-0.5 border-l border-sidebar-border/70 py-0.5 pl-2">
                        {item.children?.map((child) => (
                            <SidebarMenuSubItem key={child.title}>
                                <SidebarMenuSubButton
                                    asChild
                                    isActive={isCurrentUrl(child.href)}
                                    className="h-8 rounded-md px-2.5 text-[0.8125rem]"
                                >
                                    <Link href={child.href} prefetch>
                                        <span>{child.title}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        ))}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}
