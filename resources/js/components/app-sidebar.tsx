import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Building2,
    BriefcaseBusiness,
    Briefcase,
    CreditCard,
    FileText,
    FolderGit2,
    KeyRound,
    LayoutGrid,
    Scale,
    ShieldCheck,
    UsersRound,
    Wallet,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { firm } from '@/routes/administration';
import { index as billingItems } from '@/routes/administration/billing-items';
import { index as departments } from '@/routes/administration/departments';
import { index as expenseCategories } from '@/routes/administration/expense-categories';
import { index as matterTypes } from '@/routes/administration/matter-types';
import { index as paymentMethods } from '@/routes/administration/payment-methods';
import { index as permissions } from '@/routes/administration/permissions';
import { index as positions } from '@/routes/administration/positions';
import { index as roles } from '@/routes/administration/roles';
import { index as users } from '@/routes/administration/users';
import { index as clients } from '@/routes/clients';
import { index as matters } from '@/routes/matters';
import type { Auth, NavItem } from '@/types';

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        ...(auth.canAccessAdministration
            ? [
                  {
                      title: 'Clients',
                      href: clients(),
                      icon: BriefcaseBusiness,
                  },
                  {
                      title: 'Matters',
                      href: matters(),
                      icon: Scale,
                  },
                  {
                      title: 'Firm',
                      href: firm(),
                      icon: Building2,
                  },
                  {
                      title: 'Users',
                      href: users(),
                      icon: UsersRound,
                  },
                  {
                      title: 'Departments',
                      href: departments(),
                      icon: BriefcaseBusiness,
                  },
                  {
                      title: 'Positions',
                      href: positions(),
                      icon: Briefcase,
                  },
                  {
                      title: 'Roles',
                      href: roles(),
                      icon: ShieldCheck,
                  },
                  {
                      title: 'Permissions',
                      href: permissions(),
                      icon: KeyRound,
                  },
                  {
                      title: 'Matter types',
                      href: matterTypes(),
                      icon: Scale,
                  },
                  {
                      title: 'Billing items',
                      href: billingItems(),
                      icon: FileText,
                  },
                  {
                      title: 'Expense categories',
                      href: expenseCategories(),
                      icon: Wallet,
                  },
                  {
                      title: 'Payment methods',
                      href: paymentMethods(),
                      icon: CreditCard,
                  },
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
