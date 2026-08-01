import { Head } from '@inertiajs/react';
import ReferenceDataManagement from '@/components/administration/reference-data-management';
import { index as matterTypes } from '@/routes/administration/matter-types';

type Props = {
    matterTypes: Parameters<typeof ReferenceDataManagement>[0]['items'];
};

export default function MatterTypes({ matterTypes: items }: Props) {
    return (
        <>
            <Head title="Matter types" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-2">
                    <p className="text-sm font-medium text-primary">
                        Firm administration
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Matter types
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Classify legal matters and define their default billing
                        approach.
                    </p>
                </header>
                <ReferenceDataManagement
                    resource="matter-types"
                    title="Matter types"
                    singularTitle="matter type"
                    description="Create the matter classifications used when opening new legal work."
                    items={items}
                />
            </div>
        </>
    );
}

MatterTypes.layout = {
    breadcrumbs: [{ title: 'Matter types', href: matterTypes() }],
};
