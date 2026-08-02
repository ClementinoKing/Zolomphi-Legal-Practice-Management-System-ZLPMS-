import { Head } from '@inertiajs/react';
import UserManagement from '@/components/administration/user-management';
import { index as users } from '@/routes/administration/users';

type Props = {
    users: Parameters<typeof UserManagement>[0]['users'];
    roles: Parameters<typeof UserManagement>[0]['roles'];
    canDeleteUsers: Parameters<typeof UserManagement>[0]['canDeleteUsers'];
    departments: Parameters<typeof UserManagement>[0]['departments'];
    positions: Parameters<typeof UserManagement>[0]['positions'];
};

export default function Users({
    users: userRecords,
    roles,
    canDeleteUsers,
    departments,
    positions,
}: Props) {
    return (
        <>
            <Head title="Users" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-2">
                    <p className="text-sm font-medium text-primary">
                        Firm administration
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Users
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Invite colleagues, assign their roles, and review who
                        can access the practice workspace.
                    </p>
                </header>
                <UserManagement
                    users={userRecords}
                    roles={roles}
                    canDeleteUsers={canDeleteUsers}
                    departments={departments}
                    positions={positions}
                />
            </div>
        </>
    );
}

Users.layout = {
    breadcrumbs: [{ title: 'Users', href: users() }],
};
