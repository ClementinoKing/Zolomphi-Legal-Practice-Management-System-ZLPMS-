import { Head } from '@inertiajs/react';
import ReferenceDataManagement from '@/components/administration/reference-data-management';
import { index as billingItems } from '@/routes/administration/billing-items';

type Props = {
    billingItems: Parameters<typeof ReferenceDataManagement>[0]['items'];
};

export default function BillingItems({ billingItems: items }: Props) {
    return (
        <>
            <Head title="Billing items" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-2">
                    <p className="text-sm font-medium text-primary">
                        Firm administration
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Billing items
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Maintain reusable invoice lines, amounts, and tax
                        defaults.
                    </p>
                </header>
                <ReferenceDataManagement
                    resource="billing-items"
                    title="Billing items"
                    singularTitle="billing item"
                    description="Create reusable invoice lines for the services your firm provides."
                    items={items}
                />
            </div>
        </>
    );
}

BillingItems.layout = {
    breadcrumbs: [{ title: 'Billing items', href: billingItems() }],
};
