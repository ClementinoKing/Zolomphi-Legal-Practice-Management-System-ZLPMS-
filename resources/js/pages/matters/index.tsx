import { Head } from '@inertiajs/react';
import MatterManagement from '@/components/matter-management';
import { index as matters } from '@/routes/matters';

type Props = Parameters<typeof MatterManagement>[0];

export default function Matters(props: Props) {
    return (
        <>
            <Head title="Matters" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-2">
                    <p className="text-sm font-medium text-primary">
                        Practice management
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Matters
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Track legal work for each client and open matters
                        through a guided workflow.
                    </p>
                </header>
                <MatterManagement {...props} />
            </div>
        </>
    );
}

Matters.layout = {
    breadcrumbs: [{ title: 'Matters', href: matters() }],
};
