import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    BriefcaseBusiness,
    Briefcase,
    CalendarDays,
    ChartNoAxesCombined,
    CreditCard,
    FolderOpen,
    FileText,
    ReceiptText,
    KeyRound,
    LayoutGrid,
    Scale,
    ScrollText,
    ShieldCheck,
    UsersRound,
    Wallet,
} from 'lucide-react';
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
import { index as auditLogs } from '@/routes/audit-logs';
import { index as calendar } from '@/routes/calendar';
import { index as clients } from '@/routes/clients';
import { edit as clientSettings } from '@/routes/clients/settings';
import { index as files } from '@/routes/files';
import { index as invoices } from '@/routes/invoices';
import { edit as invoiceSettings } from '@/routes/invoices/settings';
import { index as matters } from '@/routes/matters';
import { edit as matterSettings } from '@/routes/matters/settings';
import { index as payments } from '@/routes/payments';
import { index as reports } from '@/routes/reports';
import type { Auth, NavItem } from '@/types';

export function AppSidebar() {
    const { auth } = usePage<{ auth?: Auth }>().props;
    const can = (permission: string) =>
        auth?.permissions?.includes(permission) ?? false;
    const canAny = (...permissions: string[]) =>
        permissions.some((permission) => can(permission));
    const practiceNavItems: NavItem[] = [
        ...(can('clients.view')
            ? [
                  {
                      title: 'Clients',
                      href: clients(),
                      icon: BriefcaseBusiness,
                      children: [
                          { title: 'All clients', href: clients() },
                          ...(can('clients.configure')
                              ? [
                                    {
                                        title: 'Client settings',
                                        href: clientSettings(),
                                    },
                                ]
                              : []),
                      ],
                  },
              ]
            : []),
        ...(canAny(
            'matters.view.all',
            'matters.view.department',
            'matters.view.assigned',
        )
            ? [
                  {
                      title: 'Matters',
                      href: matters(),
                      icon: Scale,
                      children: [
                          { title: 'All matters', href: matters() },
                          ...(can('matter-types.manage')
                              ? [{ title: 'Matter types', href: matterTypes() }]
                              : []),
                          ...(can('matters.configure')
                              ? [
                                    {
                                        title: 'Matter settings',
                                        href: matterSettings(),
                                    },
                                ]
                              : []),
                      ],
                  },
              ]
            : []),
        ...(can('calendar.manage')
            ? [
                  {
                      title: 'Calendar',
                      href: calendar(),
                      icon: CalendarDays,
                  },
              ]
            : []),
        ...(can('company-files.view')
            ? [
                  {
                      title: 'Files',
                      href: files(),
                      icon: FolderOpen,
                  },
              ]
            : []),
        ...(can('invoices.view')
            ? [
                  {
                      title: 'Invoices',
                      href: invoices(),
                      icon: ReceiptText,
                      children: [
                          { title: 'All invoices', href: invoices() },
                          ...(can('invoices.configure')
                              ? [
                                    {
                                        title: 'Invoice settings',
                                        href: invoiceSettings(),
                                    },
                                ]
                              : []),
                      ],
                  },
              ]
            : []),
        ...(can('payments.view')
            ? [{ title: 'Payments', href: payments(), icon: Wallet }]
            : []),
    ];

    const monitoringNavItems: NavItem[] = [
        ...(can('dashboard.view')
            ? [
                  {
                      title: 'Dashboard',
                      href: dashboard(),
                      icon: LayoutGrid,
                  },
              ]
            : []),
        ...(can('reports.view') || can('audit-logs.view')
            ? [
                  ...(can('reports.view')
                      ? [
                            {
                                title: 'Reports',
                                href: reports(),
                                icon: ChartNoAxesCombined,
                            },
                        ]
                      : []),
                  ...(can('audit-logs.view')
                      ? [
                            {
                                title: 'Audit logs',
                                href: auditLogs(),
                                icon: ScrollText,
                            },
                        ]
                      : []),
              ]
            : []),
    ];
    const firmAdministrationNavItems: NavItem[] = canAny(
        'firm-settings.manage',
        'users.manage',
        'departments.manage',
    )
        ? [
              ...(can('firm-settings.manage')
                  ? [
                        {
                            title: 'Firm',
                            href: firm(),
                            icon: Building2,
                        },
                    ]
                  : []),
              ...(can('users.manage')
                  ? [
                        {
                            title: 'Users',
                            href: users(),
                            icon: UsersRound,
                        },
                    ]
                  : []),
              ...(can('departments.manage')
                  ? [
                        {
                            title: 'Departments',
                            href: departments(),
                            icon: BriefcaseBusiness,
                        },
                    ]
                  : []),
              ...(can('departments.manage')
                  ? [
                        {
                            title: 'Positions',
                            href: positions(),
                            icon: Briefcase,
                        },
                    ]
                  : []),
          ]
        : [];
    const accessControlNavItems: NavItem[] = canAny(
        'roles.manage',
        'permissions.manage',
    )
        ? [
              ...(can('roles.manage')
                  ? [
                        {
                            title: 'Roles',
                            href: roles(),
                            icon: ShieldCheck,
                        },
                    ]
                  : []),
              ...(can('permissions.manage')
                  ? [
                        {
                            title: 'Permissions',
                            href: permissions(),
                            icon: KeyRound,
                        },
                    ]
                  : []),
          ]
        : [];
    const billingConfigurationNavItems: NavItem[] = canAny(
        'billing-items.manage',
        'expense-categories.manage',
        'payment-methods.manage',
    )
        ? [
              ...(can('billing-items.manage')
                  ? [
                        {
                            title: 'Billing items',
                            href: billingItems(),
                            icon: FileText,
                        },
                    ]
                  : []),
              ...(can('expense-categories.manage')
                  ? [
                        {
                            title: 'Expense categories',
                            href: expenseCategories(),
                            icon: Wallet,
                        },
                    ]
                  : []),
              ...(can('payment-methods.manage')
                  ? [
                        {
                            title: 'Payment methods',
                            href: paymentMethods(),
                            icon: CreditCard,
                        },
                    ]
                  : []),
          ]
        : [];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader className="px-4 pt-4 pb-3 group-data-[collapsible=icon]:p-2">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            className="h-auto min-h-18 p-0 group-data-[collapsible=icon]:size-8! group-data-[collapsible=icon]:min-h-0 hover:bg-transparent"
                            asChild
                        >
                            <Link
                                href={dashboard()}
                                prefetch
                                aria-label="Zolomphi & Co. dashboard"
                            >
                                <div className="flex w-full min-w-0 items-center justify-center">
                                    <img
                                        src="/logos/Zolomphi Favicon.svg"
                                        alt=""
                                        className="hidden size-8 shrink-0 object-contain group-data-[collapsible=icon]:block"
                                    />
                                    <img
                                        src="/logos/Zolomphi Logo Alt.svg"
                                        alt="Zolomphi & Co."
                                        className="block h-auto w-full max-w-full object-contain group-data-[collapsible=icon]:hidden"
                                    />
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain label="Monitor & control" items={monitoringNavItems} />
                <NavMain label="Practice management" items={practiceNavItems} />
                {firmAdministrationNavItems.length > 0 && (
                    <NavMain
                        label="Firm administration"
                        items={firmAdministrationNavItems}
                    />
                )}
                {accessControlNavItems.length > 0 && (
                    <NavMain
                        label="Access control"
                        items={accessControlNavItems}
                    />
                )}
                {billingConfigurationNavItems.length > 0 && (
                    <NavMain
                        label="Billing configuration"
                        items={billingConfigurationNavItems}
                    />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
