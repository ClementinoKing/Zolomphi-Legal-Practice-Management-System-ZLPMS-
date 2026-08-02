import { Head } from '@inertiajs/react';
import InvoiceBuilder from '@/components/invoice-builder';
import { index as invoices } from '@/routes/invoices';

type Props = Parameters<typeof InvoiceBuilder>[0];

export default function InvoiceBuilderPage(props: Props) {
    return (
        <>
            <Head
                title={
                    props.invoice === null
                        ? 'Build invoice'
                        : `Edit ${props.invoice.invoice_number}`
                }
            />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
                <InvoiceBuilder {...props} />
            </div>
        </>
    );
}

InvoiceBuilderPage.layout = {
    breadcrumbs: [{ title: 'Invoices', href: invoices() }],
};
