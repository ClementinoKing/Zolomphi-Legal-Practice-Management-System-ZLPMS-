import { Head } from '@inertiajs/react';
import PermissionManagement from '@/components/administration/permission-management';
import { index as permissions } from '@/routes/administration/permissions';

type Props = {
    permissions: Parameters<typeof PermissionManagement>[0]['permissions'];
};

export default function Permissions({ permissions: permissionRecords }: Props) {
    return (
        <>
            <Head title="Permissions" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-2">
                    <p className="text-sm font-medium text-primary">
                        Firm administration
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Permissions
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Define the specific actions that can be granted through
                        roles across the legal practice system.
                    </p>
                </header>
                <PermissionManagement permissions={permissionRecords} />
            </div>
        </>
    );
}

Permissions.layout = {
    breadcrumbs: [{ title: 'Permissions', href: permissions() }],
};
