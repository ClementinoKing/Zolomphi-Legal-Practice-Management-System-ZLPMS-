import { Form } from '@inertiajs/react';
import { ListChecks, Plus } from 'lucide-react';
import AdministrationController from '@/actions/App/Http/Controllers/AdministrationController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type ReferenceDataItem = {
    id: number;
    name: string;
    code: string;
    description: string | null;
    is_active: boolean;
    sort_order: number;
    default_billing_type?: string;
    default_hourly_rate?: string | null;
    unit?: string;
    default_amount?: string;
    tax_rate?: string;
    is_billable_by_default?: boolean;
};

export type ReferenceDataResource =
    'matter-types' | 'billing-items' | 'expense-categories' | 'payment-methods';

export default function ReferenceDataManagement({
    resource,
    title,
    singularTitle,
    description,
    items,
}: {
    resource: ReferenceDataResource;
    title: string;
    singularTitle: string;
    description: string;
    items: ReferenceDataItem[];
}) {
    return (
        <Card>
            <CardHeader className="gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="grid gap-1">
                    <CardTitle>{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </div>
                <ReferenceDataDialog
                    resource={resource}
                    singularTitle={singularTitle}
                    description={description}
                />
            </CardHeader>
            <CardContent>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-200 text-left text-sm">
                        <thead className="bg-muted/50 text-xs tracking-wide text-muted-foreground uppercase">
                            <tr>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    {singularTitle}
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Code
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Defaults
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Order
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {items.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        <ListChecks className="mx-auto mb-3 size-5" />
                                        No {title.toLowerCase()} configured yet.
                                    </td>
                                </tr>
                            ) : (
                                items.map((item) => (
                                    <tr
                                        key={item.id}
                                        className="transition-colors hover:bg-muted/30"
                                    >
                                        <td className="px-4 py-3 align-top">
                                            <p className="font-medium">
                                                {item.name}
                                            </p>
                                            {item.description && (
                                                <p className="mt-0.5 max-w-lg text-muted-foreground">
                                                    {item.description}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 align-top font-mono text-muted-foreground">
                                            {item.code}
                                        </td>
                                        <td className="px-4 py-3 align-top text-muted-foreground">
                                            {referenceDataDefaults(
                                                resource,
                                                item,
                                            )}
                                        </td>
                                        <td className="px-4 py-3 align-top text-muted-foreground">
                                            {item.sort_order}
                                        </td>
                                        <td className="px-4 py-3 align-top">
                                            <Badge
                                                variant={
                                                    item.is_active
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {item.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    );
}

function ReferenceDataDialog({
    resource,
    singularTitle,
    description,
}: {
    resource: ReferenceDataResource;
    singularTitle: string;
    description: string;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button>
                    <Plus />
                    Add {singularTitle}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Add {singularTitle}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <Form
                    {...AdministrationController.storeReferenceData.form(
                        resource,
                    )}
                    resetOnSuccess
                    className="grid gap-5"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    resource={resource}
                                    label="Name"
                                    name="name"
                                    error={errors.name}
                                    required
                                />
                                <Field
                                    resource={resource}
                                    label="Sort order"
                                    name="sort_order"
                                    type="number"
                                    defaultValue="0"
                                    error={errors.sort_order}
                                    required
                                />
                            </div>
                            {resource === 'matter-types' && (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <SelectField
                                        resource={resource}
                                        label="Billing type"
                                        name="default_billing_type"
                                        options={[
                                            'hourly',
                                            'fixed_fee',
                                            'contingency',
                                        ]}
                                    />
                                    <Field
                                        resource={resource}
                                        label="Default hourly rate"
                                        name="default_hourly_rate"
                                        type="number"
                                        error={errors.default_hourly_rate}
                                    />
                                </div>
                            )}
                            {resource === 'billing-items' && (
                                <div className="grid gap-4 sm:grid-cols-3">
                                    <Field
                                        resource={resource}
                                        label="Unit"
                                        name="unit"
                                        defaultValue="item"
                                        error={errors.unit}
                                        required
                                    />
                                    <Field
                                        resource={resource}
                                        label="Default amount"
                                        name="default_amount"
                                        type="number"
                                        defaultValue="0"
                                        error={errors.default_amount}
                                        required
                                    />
                                    <Field
                                        resource={resource}
                                        label="Tax rate (%)"
                                        name="tax_rate"
                                        type="number"
                                        defaultValue="0"
                                        error={errors.tax_rate}
                                        required
                                    />
                                </div>
                            )}
                            <div className="grid gap-2">
                                <Label htmlFor={`${resource}-description`}>
                                    Description
                                </Label>
                                <textarea
                                    id={`${resource}-description`}
                                    name="description"
                                    className="min-h-20 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                                />
                                <InputError message={errors.description} />
                            </div>
                            <div className="flex flex-wrap gap-5">
                                <input
                                    type="hidden"
                                    name="is_active"
                                    value="0"
                                />
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        className="size-4 accent-primary"
                                        name="is_active"
                                        type="checkbox"
                                        value="1"
                                        defaultChecked
                                    />
                                    Active
                                </label>
                                {resource === 'expense-categories' && (
                                    <>
                                        <input
                                            type="hidden"
                                            name="is_billable_by_default"
                                            value="0"
                                        />
                                        <label className="flex items-center gap-2 text-sm">
                                            <input
                                                className="size-4 accent-primary"
                                                name="is_billable_by_default"
                                                type="checkbox"
                                                value="1"
                                            />
                                            Billable by default
                                        </label>
                                    </>
                                )}
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Create {singularTitle}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function referenceDataDefaults(
    resource: ReferenceDataResource,
    item: ReferenceDataItem,
): string {
    if (resource === 'matter-types') {
        return item.default_hourly_rate
            ? `${item.default_billing_type ?? 'hourly'} · ${item.default_hourly_rate}`
            : (item.default_billing_type ?? 'hourly');
    }

    if (resource === 'billing-items') {
        return `${item.unit ?? 'item'} · ${item.default_amount ?? '0'} · ${item.tax_rate ?? '0'}% tax`;
    }

    if (resource === 'expense-categories') {
        return item.is_billable_by_default
            ? 'Billable by default'
            : 'Non-billable';
    }

    return '—';
}

function Field({
    resource,
    label,
    name,
    error,
    required = false,
    type = 'text',
    defaultValue,
}: {
    resource: ReferenceDataResource;
    label: string;
    name: string;
    error?: string;
    required?: boolean;
    type?: string;
    defaultValue?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={`${resource}-${name}`}>{label}</Label>
            <Input
                id={`${resource}-${name}`}
                name={name}
                type={type}
                step={type === 'number' ? '0.01' : undefined}
                defaultValue={defaultValue}
                required={required}
            />
            <InputError message={error} />
        </div>
    );
}

function SelectField({
    resource,
    label,
    name,
    options,
}: {
    resource: ReferenceDataResource;
    label: string;
    name: string;
    options: string[];
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={`${resource}-${name}`}>{label}</Label>
            <select
                id={`${resource}-${name}`}
                name={name}
                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
            >
                {options.map((option) => (
                    <option key={option} value={option}>
                        {option.replace('_', ' ')}
                    </option>
                ))}
            </select>
        </div>
    );
}
