import { Head } from '@inertiajs/react';
import RoleManagement from '@/components/administration/role-management';
import { index as roles } from '@/routes/administration/roles';

type Props = {
    roles: Parameters<typeof RoleManagement>[0]['roles'];
    permissions: Parameters<typeof RoleManagement>[0]['permissions'];
    canManageRoles: boolean;
};

export default function Roles({
    roles: roleRecords,
    permissions,
    canManageRoles,
}: Props) {
    return (
        <>
            <Head title="Roles" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-2">
                    <p className="text-sm font-medium text-primary">
                        Firm administration
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Roles
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Bundle permissions into clear, auditable roles for your
                        legal practice team.
                    </p>
                </header>
                <RoleManagement
                    roles={roleRecords}
                    permissions={permissions}
                    canManageRoles={canManageRoles}
                />
            </div>
        </>
    );
}

Roles.layout = {
    breadcrumbs: [{ title: 'Roles', href: roles() }],
};
