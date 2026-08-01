import { Head } from '@inertiajs/react';
import ReferenceDataManagement from '@/components/administration/reference-data-management';
import { index as paymentMethods } from '@/routes/administration/payment-methods';

type Props = {
    paymentMethods: Parameters<typeof ReferenceDataManagement>[0]['items'];
};

export default function PaymentMethods({ paymentMethods: items }: Props) {
    return (
        <>
            <Head title="Payment methods" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-2">
                    <p className="text-sm font-medium text-primary">
                        Firm administration
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Payment methods
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Control the payment options available when recording
                        receipts.
                    </p>
                </header>
                <ReferenceDataManagement
                    resource="payment-methods"
                    title="Payment methods"
                    singularTitle="payment method"
                    description="Create the receipt payment methods accepted by your firm."
                    items={items}
                />
            </div>
        </>
    );
}

PaymentMethods.layout = {
    breadcrumbs: [{ title: 'Payment methods', href: paymentMethods() }],
};
