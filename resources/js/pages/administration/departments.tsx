import { Head } from '@inertiajs/react';
import DepartmentManagement from '@/components/administration/department-management';
import { index as departments } from '@/routes/administration/departments';

type Props = {
    departments: Parameters<typeof DepartmentManagement>[0]['departments'];
};

export default function Departments({ departments: departmentRecords }: Props) {
    return (
        <>
            <Head title="Departments" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-2">
                    <p className="text-sm font-medium text-primary">
                        Firm administration
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Departments
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Define the departments in your law firm and assign them
                        when inviting team members.
                    </p>
                </header>
                <DepartmentManagement departments={departmentRecords} />
            </div>
        </>
    );
}

Departments.layout = {
    breadcrumbs: [{ title: 'Departments', href: departments() }],
};
