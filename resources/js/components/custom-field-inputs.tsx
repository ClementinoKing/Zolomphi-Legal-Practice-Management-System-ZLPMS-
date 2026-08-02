import type { Errors } from '@inertiajs/core';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { CustomFieldDefinition } from '@/types/custom-fields';

export function CustomFieldInputs({
    fields,
    values = {},
    errors = {},
    nativeRequired = false,
    dataStep,
}: {
    fields: CustomFieldDefinition[];
    values?: Record<string, boolean | number | string>;
    errors?: Errors;
    nativeRequired?: boolean;
    dataStep?: 1 | 2 | 3;
}) {
    if (fields.length === 0) {
        return null;
    }

    return (
        <section className="grid gap-4 border-t pt-5">
            <div>
                <h3 className="font-medium">Additional information</h3>
                <p className="mt-1 text-sm text-muted-foreground">
                    Complete the fields configured for this record.
                </p>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                {fields.map((field) => (
                    <CustomFieldInput
                        key={field.key}
                        field={field}
                        value={values[field.key]}
                        error={errors[`custom_fields.${field.key}`]}
                        nativeRequired={nativeRequired}
                        dataStep={dataStep}
                    />
                ))}
            </div>
        </section>
    );
}

function CustomFieldInput({
    field,
    value,
    error,
    nativeRequired,
    dataStep,
}: {
    field: CustomFieldDefinition;
    value: boolean | number | string | undefined;
    error?: string;
    nativeRequired: boolean;
    dataStep?: 1 | 2 | 3;
}) {
    const name = `custom_fields[${field.key}]`;
    const id = `custom-field-${field.key}`;

    if (field.type === 'boolean') {
        return (
            <div className="flex items-start gap-3 rounded-lg border border-border/70 px-3 py-3 sm:col-span-2">
                <input type="hidden" name={name} value="0" />
                <input
                    id={id}
                    type="checkbox"
                    name={name}
                    value="1"
                    data-client-step={dataStep}
                    defaultChecked={
                        value === true || value === 1 || value === '1'
                    }
                    className="mt-0.5 size-4 accent-primary"
                />
                <Label htmlFor={id} className="grid gap-1 text-sm">
                    <span>{field.label}</span>
                    {field.placeholder !== '' && (
                        <span className="font-normal text-muted-foreground">
                            {field.placeholder}
                        </span>
                    )}
                    {error && (
                        <span className="font-normal text-destructive">
                            {error}
                        </span>
                    )}
                </Label>
            </div>
        );
    }

    return (
        <div
            className={
                field.type === 'textarea'
                    ? 'grid gap-2 sm:col-span-2'
                    : 'grid gap-2'
            }
        >
            <Label htmlFor={id}>
                {field.label}
                {field.required && (
                    <span className="ml-1 text-destructive">*</span>
                )}
            </Label>
            {field.type === 'textarea' ? (
                <textarea
                    id={id}
                    name={name}
                    defaultValue={String(value ?? '')}
                    data-client-step={dataStep}
                    placeholder={field.placeholder}
                    required={nativeRequired && field.required}
                    className="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                />
            ) : field.type === 'select' ? (
                <select
                    id={id}
                    name={name}
                    defaultValue={String(value ?? '')}
                    data-client-step={dataStep}
                    required={nativeRequired && field.required}
                    className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                >
                    <option value="">
                        Select {field.label.toLocaleLowerCase()}
                    </option>
                    {field.options.map((option) => (
                        <option key={option} value={option}>
                            {option}
                        </option>
                    ))}
                </select>
            ) : (
                <Input
                    id={id}
                    name={name}
                    type={field.type}
                    defaultValue={String(value ?? '')}
                    data-client-step={dataStep}
                    placeholder={field.placeholder}
                    required={nativeRequired && field.required}
                />
            )}
            {error && <span className="text-sm text-destructive">{error}</span>}
        </div>
    );
}
