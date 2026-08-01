import { Head } from '@inertiajs/react';
import PositionManagement from '@/components/administration/position-management';
import { index as positions } from '@/routes/administration/positions';

type Props = {
    positions: Parameters<typeof PositionManagement>[0]['positions'];
    departments: Parameters<typeof PositionManagement>[0]['departments'];
};

export default function Positions({
    positions: positionRecords,
    departments,
}: Props) {
    return (
        <>
            <Head title="Positions" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-2">
                    <p className="text-sm font-medium text-primary">
                        Firm administration
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Positions
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Maintain the positions within every department of your
                        law firm.
                    </p>
                </header>
                <PositionManagement
                    positions={positionRecords}
                    departments={departments}
                />
            </div>
        </>
    );
}

Positions.layout = {
    breadcrumbs: [{ title: 'Positions', href: positions() }],
};
