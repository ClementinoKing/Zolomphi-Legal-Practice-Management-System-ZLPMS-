import { Head } from '@inertiajs/react';
import InvoiceManagement from '@/components/invoice-management';
import { index as invoices } from '@/routes/invoices';

type Props = Parameters<typeof InvoiceManagement>[0];

export default function InvoiceIndex(props: Props) {
    return (
        <>
            <Head title="Invoices" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-2">
                    <p className="text-sm font-medium text-primary">
                        Practice billing
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Invoices
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Build accurate client invoices from approved legal work
                        and keep draft billing records organised.
                    </p>
                </header>
                <InvoiceManagement {...props} />
            </div>
        </>
    );
}

InvoiceIndex.layout = {
    breadcrumbs: [{ title: 'Invoices', href: invoices() }],
};
