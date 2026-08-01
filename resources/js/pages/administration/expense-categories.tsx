import { Head } from '@inertiajs/react';
import ReferenceDataManagement from '@/components/administration/reference-data-management';
import { index as expenseCategories } from '@/routes/administration/expense-categories';

type Props = {
    expenseCategories: Parameters<typeof ReferenceDataManagement>[0]['items'];
};

export default function ExpenseCategories({ expenseCategories: items }: Props) {
    return (
        <>
            <Head title="Expense categories" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-2">
                    <p className="text-sm font-medium text-primary">
                        Firm administration
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Expense categories
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Classify disbursements and define their default billing
                        treatment.
                    </p>
                </header>
                <ReferenceDataManagement
                    resource="expense-categories"
                    title="Expense categories"
                    singularTitle="expense category"
                    description="Create categories for expenses recorded against client matters."
                    items={items}
                />
            </div>
        </>
    );
}

ExpenseCategories.layout = {
    breadcrumbs: [{ title: 'Expense categories', href: expenseCategories() }],
};
