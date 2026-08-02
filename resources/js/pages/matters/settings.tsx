import { Head } from '@inertiajs/react';
import PracticeRecordSettingsForm from '@/components/practice-record-settings-form';
import { index as matters } from '@/routes/matters';
import { edit, update } from '@/routes/matters/settings';
import type { CustomFieldDefinition } from '@/types/custom-fields';

export default function MatterSettings({ settings }: { settings: { number_prefix: string; number_padding: number; next_number: number; fields: CustomFieldDefinition[] } }) {
    return <><Head title="Matter settings" /><div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6"><header><p className="text-sm font-medium text-primary">Practice management</p><h1 className="mt-2 text-2xl font-semibold tracking-tight">Matter settings</h1><p className="mt-2 text-sm text-muted-foreground">Configure matter numbering and the fields used in the opening workflow.</p></header><PracticeRecordSettingsForm kind="matter" settings={settings} submitUrl={update().url} /></div></>;
}

MatterSettings.layout = { breadcrumbs: [{ title: 'Matters', href: matters() }, { title: 'Matter settings', href: edit() }] };
