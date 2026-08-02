import { Head } from '@inertiajs/react';
import ClientManagement from '@/components/client-management';
import { index as clients } from '@/routes/clients';

type Props = {
    clients: Parameters<typeof ClientManagement>[0]['clients'];
    customFields: Parameters<typeof ClientManagement>[0]['customFields'];
};

export default function Clients({ clients: clientRecords, customFields }: Props) {
    return (
        <>
            <Head title="Clients" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-2">
                    <p className="text-sm font-medium text-primary">
                        Practice management
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Clients
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Create and manage the individuals and organizations your
                        firm represents. Each client can be connected to one or
                        more legal matters.
                    </p>
                </header>
                <ClientManagement clients={clientRecords} customFields={customFields} />
            </div>
        </>
    );
}

Clients.layout = {
    breadcrumbs: [{ title: 'Clients', href: clients() }],
};
