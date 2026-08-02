import { Head } from '@inertiajs/react';
import PracticeRecordSettingsForm from '@/components/practice-record-settings-form';
import { index as clients } from '@/routes/clients';
import { edit, update } from '@/routes/clients/settings';
import type { CustomFieldDefinition } from '@/types/custom-fields';

export default function ClientSettings({ settings }: { settings: { number_prefix: string; number_padding: number; next_number: number; fields: CustomFieldDefinition[] } }) {
    return <><Head title="Client settings" /><div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6"><header><p className="text-sm font-medium text-primary">Practice management</p><h1 className="mt-2 text-2xl font-semibold tracking-tight">Client settings</h1><p className="mt-2 text-sm text-muted-foreground">Configure client numbering and the information your firm captures.</p></header><PracticeRecordSettingsForm kind="client" settings={settings} submitUrl={update().url} /></div></>;
}

ClientSettings.layout = { breadcrumbs: [{ title: 'Clients', href: clients() }, { title: 'Client settings', href: edit() }] };
