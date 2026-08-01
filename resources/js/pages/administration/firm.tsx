import { Head } from '@inertiajs/react';
import FirmSettingsForm from '@/components/administration/firm-settings-form';
import { firm } from '@/routes/administration';

type Props = {
    firmSettings: Parameters<typeof FirmSettingsForm>[0]['settings'];
};

export default function Firm({ firmSettings }: Props) {
    return (
        <>
            <Head title="Firm" />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-2">
                    <p className="text-sm font-medium text-primary">
                        Firm administration
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Firm settings
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Maintain the identity, contact details, tax information,
                        and regional defaults for your practice.
                    </p>
                </header>
                <FirmSettingsForm settings={firmSettings} />
            </div>
        </>
    );
}

Firm.layout = {
    breadcrumbs: [{ title: 'Firm', href: firm() }],
};
