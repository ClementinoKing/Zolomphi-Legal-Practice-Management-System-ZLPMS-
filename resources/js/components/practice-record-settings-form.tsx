import { useForm } from '@inertiajs/react';
import { Pencil, Plus, Save, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type {
    CustomFieldDefinition,
    CustomFieldType,
} from '@/types/custom-fields';

type Settings = {
    number_prefix: string;
    number_padding: number;
    next_number: number;
    fields: CustomFieldDefinition[];
};

type EditableField = Omit<CustomFieldDefinition, 'key' | 'sort_order'>;

const fieldTypes: Array<{ value: CustomFieldType; label: string }> = [
    { value: 'text', label: 'Text' },
    { value: 'textarea', label: 'Long text' },
    { value: 'number', label: 'Number' },
    { value: 'date', label: 'Date' },
    { value: 'select', label: 'Select list' },
    { value: 'boolean', label: 'Yes / no' },
];

const matterStepLabels = [
    'Matter details',
    'Billing and status',
    'Participants',
];

const clientStepLabels = [
    'Client profile',
    'Contact and address',
    'Additional information',
];

export default function PracticeRecordSettingsForm({
    kind,
    settings,
    submitUrl,
}: {
    kind: 'client' | 'matter';
    settings: Settings;
    submitUrl: string;
}) {
    const form = useForm<Settings>(settings);
    const [editingField, setEditingField] = useState<EditableField | null>(
        null,
    );
    const [optionsText, setOptionsText] = useState('');
    const isClient = kind === 'client';
    const stepLabels = isClient ? clientStepLabels : matterStepLabels;
    const nextNumber = `${form.data.number_prefix}${isClient ? '' : '-TYPE'}-${String(form.data.next_number).padStart(form.data.number_padding, '0')}`;

    function openField(field?: CustomFieldDefinition): void {
        const record: EditableField = field
            ? {
                  id: field.id,
                  label: field.label,
                  type: field.type,
                  placeholder: field.placeholder,
                  options: field.options,
                  required: field.required,
                  applies_to: field.applies_to,
                  step: field.step,
              }
            : {
                  id: `new-${Date.now()}`,
                  label: '',
                  type: 'text',
                  placeholder: '',
                  options: [],
                  required: false,
                  applies_to: ['individual', 'organization'],
                  step: 1,
              };

        setEditingField(record);
        setOptionsText(record.options.join('\n'));
    }

    function saveField(): void {
        if (editingField === null || editingField.label.trim() === '') {
            return;
        }

        const field = {
            ...editingField,
            label: editingField.label.trim(),
            options:
                editingField.type === 'select'
                    ? optionsText
                          .split('\n')
                          .map((option) => option.trim())
                          .filter(Boolean)
                    : [],
        };
        const existingIndex = form.data.fields.findIndex(
            (current) => current.id === field.id,
        );
        const fields =
            existingIndex === -1
                ? [
                      ...form.data.fields,
                      {
                          ...field,
                          key: '',
                          sort_order: form.data.fields.length,
                      },
                  ]
                : form.data.fields.map((current, index) =>
                      index === existingIndex
                          ? { ...current, ...field }
                          : current,
                  );

        form.setData('fields', fields);
        setEditingField(null);
    }

    return (
        <form
            className="grid gap-6"
            onSubmit={(event) => {
                event.preventDefault();
                form.put(submitUrl, { preserveScroll: true });
            }}
        >
            <section className="rounded-xl border border-border/70 bg-background p-5">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 className="font-semibold">Number generation</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {isClient
                                ? `The next record will be generated as ${nextNumber}.`
                                : `Matter numbers use the prefix, matter type short code, and sequence, for example ${nextNumber}.`}
                        </p>
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? <Spinner /> : <Save />}
                        Save settings
                    </Button>
                </div>
                <div className="mt-5 grid gap-4 sm:grid-cols-3">
                    <Field label="Prefix" error={form.errors.number_prefix}>
                        <Input
                            value={form.data.number_prefix}
                            onChange={(event) =>
                                form.setData(
                                    'number_prefix',
                                    event.target.value.toUpperCase(),
                                )
                            }
                        />
                    </Field>
                    <Field label="Digits" error={form.errors.number_padding}>
                        <Input
                            type="number"
                            min="3"
                            max="10"
                            value={form.data.number_padding}
                            onChange={(event) =>
                                form.setData(
                                    'number_padding',
                                    Number(event.target.value),
                                )
                            }
                        />
                    </Field>
                    <Field label="Next number" error={form.errors.next_number}>
                        <Input
                            type="number"
                            min="1"
                            value={form.data.next_number}
                            onChange={(event) =>
                                form.setData(
                                    'next_number',
                                    Number(event.target.value),
                                )
                            }
                        />
                    </Field>
                </div>
            </section>

            <section className="rounded-xl border border-border/70 bg-background p-5">
                <div className="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 className="font-semibold">Additional fields</h2>
                        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                            {isClient
                                ? 'Configure fields for individuals, organisations, or both, then place each field in the right client-form step.'
                                : 'Place fields in the relevant step of the matter-opening workflow.'}
                        </p>
                    </div>
                    <Button type="button" onClick={() => openField()}>
                        <Plus /> Add field
                    </Button>
                </div>
                {form.data.fields.length === 0 ? (
                    <div className="mt-5 rounded-lg border border-dashed px-4 py-12 text-center text-sm text-muted-foreground">
                        No additional fields have been configured.
                    </div>
                ) : (
                    <div className="mt-5 overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-180 text-left text-sm">
                            <thead className="bg-muted/50 text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Field
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Type
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Applies to
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Form step
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Required
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {form.data.fields.map((field) => (
                                    <tr key={field.id}>
                                        <td className="px-4 py-3">
                                            <p className="font-medium">
                                                {field.label}
                                            </p>
                                            <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                                                {field.key ||
                                                    'Generated when saved'}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3 capitalize">
                                            {field.type.replace('_', ' ')}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {isClient
                                                ? field.applies_to.join(' and ')
                                                : 'All matter types'}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            Step {field.step}:{' '}
                                            {stepLabels[field.step - 1]}
                                        </td>
                                        <td className="px-4 py-3">
                                            {field.required
                                                ? 'Required'
                                                : 'Optional'}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    aria-label={`Edit ${field.label}`}
                                                    onClick={() =>
                                                        openField(field)
                                                    }
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    aria-label={`Remove ${field.label}`}
                                                    onClick={() =>
                                                        form.setData(
                                                            'fields',
                                                            form.data.fields.filter(
                                                                (current) =>
                                                                    current.id !==
                                                                    field.id,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <Trash2 className="size-4 text-destructive" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>

            <Dialog
                open={editingField !== null}
                onOpenChange={(open) => !open && setEditingField(null)}
            >
                <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>
                            {editingField?.id.startsWith('new-')
                                ? 'Add field'
                                : 'Edit field'}
                        </DialogTitle>
                    </DialogHeader>
                    {editingField !== null && (
                        <div className="grid gap-4">
                            <Field label="Field label">
                                <Input
                                    value={editingField.label}
                                    onChange={(event) =>
                                        setEditingField({
                                            ...editingField,
                                            label: event.target.value,
                                        })
                                    }
                                />
                            </Field>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Field type">
                                    <select
                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs"
                                        value={editingField.type}
                                        onChange={(event) =>
                                            setEditingField({
                                                ...editingField,
                                                type: event.target
                                                    .value as CustomFieldType,
                                            })
                                        }
                                    >
                                        {fieldTypes.map((fieldType) => (
                                            <option
                                                key={fieldType.value}
                                                value={fieldType.value}
                                            >
                                                {fieldType.label}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field
                                    label={
                                        isClient
                                            ? 'Client form step'
                                            : 'Opening step'
                                    }
                                >
                                    <select
                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs"
                                        value={editingField.step}
                                        onChange={(event) =>
                                            setEditingField({
                                                ...editingField,
                                                step: Number(
                                                    event.target.value,
                                                ) as 1 | 2 | 3,
                                            })
                                        }
                                    >
                                        {stepLabels.map((step, index) => (
                                            <option
                                                key={step}
                                                value={index + 1}
                                            >
                                                Step {index + 1}: {step}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                            </div>
                            {editingField.type !== 'boolean' && (
                                <Field label="Help text or placeholder">
                                    <Input
                                        value={editingField.placeholder}
                                        onChange={(event) =>
                                            setEditingField({
                                                ...editingField,
                                                placeholder: event.target.value,
                                            })
                                        }
                                    />
                                </Field>
                            )}
                            {editingField.type === 'select' && (
                                <Field label="Options (one per line)">
                                    <textarea
                                        className="min-h-28 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs"
                                        value={optionsText}
                                        onChange={(event) =>
                                            setOptionsText(event.target.value)
                                        }
                                    />
                                </Field>
                            )}
                            {isClient && (
                                <div className="grid gap-2">
                                    <Label>Applies to</Label>
                                    <div className="grid grid-cols-2 gap-3 rounded-lg border p-3 text-sm">
                                        {(
                                            [
                                                'individual',
                                                'organization',
                                            ] as const
                                        ).map((type) => (
                                            <label
                                                key={type}
                                                className="flex items-center gap-2 capitalize"
                                            >
                                                <Checkbox
                                                    checked={editingField.applies_to.includes(
                                                        type,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        setEditingField({
                                                            ...editingField,
                                                            applies_to:
                                                                checked === true
                                                                    ? [
                                                                          ...new Set(
                                                                              [
                                                                                  ...editingField.applies_to,
                                                                                  type,
                                                                              ],
                                                                          ),
                                                                      ]
                                                                    : editingField.applies_to.filter(
                                                                          (
                                                                              current,
                                                                          ) =>
                                                                              current !==
                                                                              type,
                                                                      ),
                                                        })
                                                    }
                                                />
                                                {type}
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            )}
                            <label className="flex items-center gap-3 rounded-lg border p-3 text-sm">
                                <Checkbox
                                    checked={editingField.required}
                                    onCheckedChange={(checked) =>
                                        setEditingField({
                                            ...editingField,
                                            required: checked === true,
                                        })
                                    }
                                />
                                Required field
                            </label>
                        </div>
                    )}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setEditingField(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            disabled={editingField?.label.trim() === ''}
                            onClick={saveField}
                        >
                            Save field
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </form>
    );
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <label className="grid gap-1.5 text-sm font-medium">
            {label}
            {children}
            {error && (
                <span className="text-xs font-normal text-destructive">
                    {error}
                </span>
            )}
        </label>
    );
}
