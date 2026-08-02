import { Form } from '@inertiajs/react';
import {
    ArrowDownUp,
    Building2,
    Check,
    ChevronLeft,
    ChevronRight,
    Pencil,
    Plus,
    Search,
    UserRound,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import ClientController from '@/actions/App/Http/Controllers/ClientController';
import { CustomFieldInputs } from '@/components/custom-field-inputs';
import InputError from '@/components/input-error';
import SearchableSelect from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import type { CustomFieldDefinition } from '@/types/custom-fields';

type Client = {
    id: number;
    client_type: 'individual' | 'organization';
    name: string;
    code: string;
    contact_person: string | null;
    email: string | null;
    phone: string | null;
    alternate_phone: string | null;
    tax_number: string | null;
    registration_number: string | null;
    address_line1: string | null;
    address_line2: string | null;
    city: string | null;
    state: string | null;
    postal_code: string | null;
    country: string | null;
    notes: string | null;
    is_active: boolean;
    custom_fields: Record<string, boolean | number | string>;
};

type SortField = 'name' | 'client_type' | 'code';

const pageSize = 10;

const clientFormSteps = [
    {
        title: 'Client profile',
        description: 'Identify the person or organisation you represent.',
    },
    {
        title: 'Contact and address',
        description: 'Capture contact, registration, and location details.',
    },
    {
        title: 'Additional information',
        description: 'Complete configured fields and confirm record status.',
    },
] as const;

export default function ClientManagement({
    clients,
    customFields,
}: {
    clients: Client[];
    customFields: CustomFieldDefinition[];
}) {
    const [search, setSearch] = useState('');
    const [typeFilter, setTypeFilter] = useState('all');
    const [statusFilter, setStatusFilter] = useState('active');
    const [sortField, setSortField] = useState<SortField>('name');
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('asc');
    const [page, setPage] = useState(1);

    const filteredClients = useMemo(() => {
        const searchTerm = search.trim().toLocaleLowerCase();

        return clients
            .filter((client) => {
                const matchesSearch =
                    searchTerm === '' ||
                    [
                        client.name,
                        client.code,
                        client.contact_person,
                        client.email,
                        client.phone,
                    ].some((value) =>
                        value?.toLocaleLowerCase().includes(searchTerm),
                    );
                const matchesType =
                    typeFilter === 'all' || client.client_type === typeFilter;
                const matchesStatus =
                    statusFilter === 'all' ||
                    (statusFilter === 'active' && client.is_active) ||
                    (statusFilter === 'inactive' && !client.is_active);

                return matchesSearch && matchesType && matchesStatus;
            })
            .sort((first, second) => {
                const comparison = first[sortField].localeCompare(
                    second[sortField],
                );

                return sortDirection === 'asc' ? comparison : -comparison;
            });
    }, [clients, search, sortDirection, sortField, statusFilter, typeFilter]);

    const totalPages = Math.max(
        1,
        Math.ceil(filteredClients.length / pageSize),
    );
    const currentPage = Math.min(page, totalPages);
    const visibleClients = filteredClients.slice(
        (currentPage - 1) * pageSize,
        currentPage * pageSize,
    );

    function updateSort(field: SortField): void {
        if (sortField === field) {
            setSortDirection((direction) =>
                direction === 'asc' ? 'desc' : 'asc',
            );
        } else {
            setSortField(field);
            setSortDirection('asc');
        }

        setPage(1);
    }

    function resetPage(): void {
        setPage(1);
    }

    return (
        <section className="grid gap-5">
            <header className="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-start sm:justify-between">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="grid gap-1">
                        <p className="text-sm text-muted-foreground">
                            {filteredClients.length} of {clients.length} client
                            {clients.length === 1 ? '' : 's'} in the practice
                            workspace.
                        </p>
                    </div>
                </div>
                <ClientDialog customFields={customFields} />
            </header>
            <div className="grid gap-3 rounded-xl bg-muted/35 p-3 md:grid-cols-[minmax(0,1fr)_11rem_9rem]">
                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        aria-label="Search clients"
                        className="bg-background pl-9"
                        placeholder="Search by client, code, contact, email, or phone"
                        value={search}
                        onChange={(event) => {
                            setSearch(event.target.value);
                            resetPage();
                        }}
                    />
                </div>
                <select
                    aria-label="Filter clients by type"
                    className="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs"
                    value={typeFilter}
                    onChange={(event) => {
                        setTypeFilter(event.target.value);
                        resetPage();
                    }}
                >
                    <option value="all">All types</option>
                    <option value="individual">Individuals</option>
                    <option value="organization">Organizations</option>
                </select>
                <select
                    aria-label="Filter clients by status"
                    className="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs"
                    value={statusFilter}
                    onChange={(event) => {
                        setStatusFilter(event.target.value);
                        resetPage();
                    }}
                >
                    <option value="all">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div className="grid gap-4">
                <div className="overflow-x-auto rounded-xl border border-border/70 bg-background">
                    <table className="w-full min-w-250 text-left text-sm">
                        <thead className="bg-muted/50 text-xs tracking-wide text-muted-foreground uppercase">
                            <tr>
                                <SortableHeader
                                    field="name"
                                    label="Client"
                                    sortField={sortField}
                                    sortDirection={sortDirection}
                                    onSort={updateSort}
                                />
                                <SortableHeader
                                    field="client_type"
                                    label="Type"
                                    sortField={sortField}
                                    sortDirection={sortDirection}
                                    onSort={updateSort}
                                />
                                <SortableHeader
                                    field="code"
                                    label="Code"
                                    sortField={sortField}
                                    sortDirection={sortDirection}
                                    onSort={updateSort}
                                />
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Contact details
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Status
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 text-right font-medium"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {visibleClients.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        <Building2 className="mx-auto mb-3 size-5" />
                                        No clients match the selected filters.
                                    </td>
                                </tr>
                            ) : (
                                visibleClients.map((client) => (
                                    <tr
                                        key={client.id}
                                        className="transition-colors hover:bg-muted/30"
                                    >
                                        <td className="px-4 py-3 align-top">
                                            <p className="font-medium">
                                                {client.name}
                                            </p>
                                            {client.contact_person && (
                                                <p className="mt-0.5 text-muted-foreground">
                                                    {client.contact_person}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 align-top text-muted-foreground">
                                            <span className="inline-flex items-center gap-1.5">
                                                {client.client_type ===
                                                'individual' ? (
                                                    <UserRound className="size-3.5" />
                                                ) : (
                                                    <Building2 className="size-3.5" />
                                                )}
                                                {client.client_type ===
                                                'individual'
                                                    ? 'Individual'
                                                    : 'Organization'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 align-top font-mono text-muted-foreground">
                                            {client.code}
                                        </td>
                                        <td className="px-4 py-3 align-top text-muted-foreground">
                                            <div className="grid gap-0.5">
                                                {client.email && (
                                                    <span>{client.email}</span>
                                                )}
                                                {client.phone && (
                                                    <span>{client.phone}</span>
                                                )}
                                                {!client.email &&
                                                    !client.phone && (
                                                        <span>—</span>
                                                    )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 align-top">
                                            <Badge
                                                variant={
                                                    client.is_active
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {client.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-right align-top">
                                            <ClientDialog
                                                client={client}
                                                customFields={customFields}
                                            />
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="mt-4 flex flex-col gap-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                    <p>
                        {filteredClients.length === 0
                            ? 'No clients found'
                            : `Showing ${(currentPage - 1) * pageSize + 1}–${Math.min(currentPage * pageSize, filteredClients.length)} of ${filteredClients.length} clients`}
                    </p>
                    <div className="flex items-center gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={currentPage === 1}
                            onClick={() => setPage((value) => value - 1)}
                        >
                            Previous
                        </Button>
                        <span>
                            Page {currentPage} of {totalPages}
                        </span>
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={currentPage === totalPages}
                            onClick={() => setPage((value) => value + 1)}
                        >
                            Next
                        </Button>
                    </div>
                </div>
            </div>
        </section>
    );
}

function SortableHeader({
    field,
    label,
    sortField,
    sortDirection,
    onSort,
}: {
    field: SortField;
    label: string;
    sortField: SortField;
    sortDirection: 'asc' | 'desc';
    onSort: (field: SortField) => void;
}) {
    const isCurrentField = sortField === field;

    return (
        <th scope="col" className="px-4 py-3 font-medium">
            <button
                className="inline-flex items-center gap-1 hover:text-foreground"
                type="button"
                onClick={() => onSort(field)}
            >
                {label}
                <ArrowDownUp
                    className={
                        isCurrentField ? 'size-3.5 text-foreground' : 'size-3.5'
                    }
                    aria-label={
                        isCurrentField
                            ? `Sorted ${sortDirection === 'asc' ? 'ascending' : 'descending'}`
                            : undefined
                    }
                />
            </button>
        </th>
    );
}

function ClientDialog({
    client,
    customFields,
}: {
    client?: Client;
    customFields: CustomFieldDefinition[];
}) {
    const [isOpen, setIsOpen] = useState(false);
    const [currentStep, setCurrentStep] = useState(1);
    const [clientType, setClientType] = useState<Client['client_type']>(
        client?.client_type ?? 'organization',
    );
    const isEditing = client !== undefined;
    const applicableFields = customFields.filter((field) =>
        field.applies_to.includes(clientType),
    );

    function handleOpenChange(open: boolean): void {
        setIsOpen(open);

        if (open) {
            setCurrentStep(1);
            setClientType(client?.client_type ?? 'organization');
        }
    }

    function focusStepWithError(errors: Record<string, string>): void {
        const errorKeys = Object.keys(errors);
        const fieldsByStep: Record<number, string[]> = {
            1: [
                'type',
                'name',
                'full_name',
                'organization_name',
                'contact_person',
                'email',
                'phone',
                'alternate_phone',
            ],
            2: [
                'tax_number',
                'registration_number',
                'address_line1',
                'address_line2',
                'city',
                'state',
                'postal_code',
                'country',
            ],
            3: ['notes', 'status'],
        };

        const customField = applicableFields.find((field) =>
            errorKeys.includes(`custom_fields.${field.key}`),
        );

        if (customField !== undefined) {
            setCurrentStep(customField.step);

            return;
        }

        const step = [1, 2, 3].find((candidate) =>
            fieldsByStep[candidate].some((field) => errorKeys.includes(field)),
        );

        setCurrentStep(step ?? 1);
    }

    return (
        <Dialog open={isOpen} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>
                {isEditing ? (
                    <Button size="sm" variant="outline">
                        <Pencil />
                        Edit
                    </Button>
                ) : (
                    <Button>
                        <Plus />
                        Add client
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-x-hidden overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit client' : 'Add client'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update the client contact record and its availability for new matters.'
                            : 'Create the primary person or organization record before opening matters.'}
                    </DialogDescription>
                </DialogHeader>
                <ol className="grid gap-2 border-y py-4 sm:grid-cols-3">
                    {clientFormSteps.map((step, index) => {
                        const stepNumber = index + 1;
                        const isCurrent = currentStep === stepNumber;
                        const isComplete = currentStep > stepNumber;

                        return (
                            <li
                                key={step.title}
                                className="flex min-w-0 items-center gap-2"
                            >
                                <span
                                    className={`flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold ${
                                        isCurrent || isComplete
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground'
                                    }`}
                                >
                                    {isComplete ? (
                                        <Check className="size-3.5" />
                                    ) : (
                                        stepNumber
                                    )}
                                </span>
                                <span
                                    className={`truncate text-sm font-medium ${
                                        isCurrent
                                            ? 'text-foreground'
                                            : 'text-muted-foreground'
                                    }`}
                                >
                                    {step.title}
                                </span>
                            </li>
                        );
                    })}
                </ol>
                <Form
                    key={client?.id ?? 'new-client'}
                    {...(client
                        ? ClientController.update.form(client)
                        : ClientController.store.form())}
                    resetOnSuccess
                    className="grid min-w-0 gap-5"
                    onSubmit={(event) => {
                        if (currentStep < clientFormSteps.length) {
                            event.preventDefault();
                        }
                    }}
                    onInvalidCapture={(event) => {
                        const step = Number(
                            (event.target as HTMLElement).dataset.clientStep,
                        );

                        if (step >= 1 && step <= clientFormSteps.length) {
                            setCurrentStep(step);
                        }
                    }}
                    onError={focusStepWithError}
                    onSuccess={() => {
                        setIsOpen(false);
                        setCurrentStep(1);
                    }}
                >
                    {({ errors, processing }) => (
                        <>
                            <section
                                hidden={currentStep !== 1}
                                className="grid gap-5"
                            >
                                <StepHeading step={1} />
                                <div className="grid min-w-0 gap-4 sm:grid-cols-2">
                                    <div className="grid min-w-0 gap-2">
                                        <Label htmlFor="client-type">
                                            Client type
                                        </Label>
                                        <SearchableSelect
                                            id="client-type"
                                            name="type"
                                            value={clientType}
                                            dataStep={1}
                                            options={[
                                                {
                                                    value: 'organization',
                                                    label: 'Organization',
                                                },
                                                {
                                                    value: 'individual',
                                                    label: 'Individual',
                                                },
                                            ]}
                                            searchable={false}
                                            onChange={(nextValue) =>
                                                setClientType(
                                                    nextValue as Client['client_type'],
                                                )
                                            }
                                            error={errors.type}
                                        />
                                    </div>
                                    <TextField
                                        label="Client or organization name"
                                        name="name"
                                        error={errors.name}
                                        defaultValue={client?.name}
                                        placeholder="Chisomo Holdings Limited"
                                        required
                                        step={1}
                                    />
                                    <TextField
                                        label="Primary contact"
                                        name="contact_person"
                                        error={errors.contact_person}
                                        defaultValue={
                                            client?.contact_person ?? ''
                                        }
                                        placeholder="Annie Chisomo"
                                        step={1}
                                    />
                                    <TextField
                                        label="Email address"
                                        name="email"
                                        type="email"
                                        error={errors.email}
                                        defaultValue={client?.email ?? ''}
                                        placeholder="legal@example.com"
                                        step={1}
                                    />
                                    <TextField
                                        label="Phone number"
                                        name="phone"
                                        error={errors.phone}
                                        defaultValue={client?.phone ?? ''}
                                        placeholder="+265 999 000 000"
                                        step={1}
                                    />
                                    <TextField
                                        label="Alternate phone"
                                        name="alternate_phone"
                                        error={errors.alternate_phone}
                                        defaultValue={
                                            client?.alternate_phone ?? ''
                                        }
                                        step={1}
                                    />
                                    <div className="grid gap-2">
                                        <Label htmlFor="client-code">
                                            Client code
                                        </Label>
                                        <Input
                                            id="client-code"
                                            value={
                                                client?.code ??
                                                'Generated automatically when saved'
                                            }
                                            readOnly
                                            className="bg-muted/40 text-muted-foreground"
                                        />
                                    </div>
                                </div>
                                <CustomFieldInputs
                                    fields={applicableFields.filter(
                                        (field) => field.step === 1,
                                    )}
                                    values={client?.custom_fields}
                                    errors={errors}
                                    nativeRequired
                                    dataStep={1}
                                />
                            </section>
                            <section
                                hidden={currentStep !== 2}
                                className="grid gap-5"
                            >
                                <StepHeading step={2} />
                                <div className="grid min-w-0 gap-4 sm:grid-cols-2">
                                    <TextField
                                        label="Tax number"
                                        name="tax_number"
                                        error={errors.tax_number}
                                        defaultValue={client?.tax_number ?? ''}
                                        step={2}
                                    />
                                    <TextField
                                        label="Registration number"
                                        name="registration_number"
                                        error={errors.registration_number}
                                        defaultValue={
                                            client?.registration_number ?? ''
                                        }
                                        step={2}
                                    />
                                    <TextField
                                        label="Address line 1"
                                        name="address_line1"
                                        error={errors.address_line1}
                                        defaultValue={
                                            client?.address_line1 ?? ''
                                        }
                                        step={2}
                                    />
                                    <TextField
                                        label="Address line 2"
                                        name="address_line2"
                                        error={errors.address_line2}
                                        defaultValue={
                                            client?.address_line2 ?? ''
                                        }
                                        step={2}
                                    />
                                    <TextField
                                        label="City"
                                        name="city"
                                        error={errors.city}
                                        defaultValue={client?.city ?? ''}
                                        step={2}
                                    />
                                    <TextField
                                        label="Country"
                                        name="country"
                                        error={errors.country}
                                        defaultValue={client?.country ?? ''}
                                        step={2}
                                    />
                                </div>
                                <CustomFieldInputs
                                    fields={applicableFields.filter(
                                        (field) => field.step === 2,
                                    )}
                                    values={client?.custom_fields}
                                    errors={errors}
                                    nativeRequired
                                    dataStep={2}
                                />
                            </section>
                            <section
                                hidden={currentStep !== 3}
                                className="grid gap-5"
                            >
                                <StepHeading step={3} />
                                <TextAreaField
                                    label="Internal notes"
                                    name="notes"
                                    error={errors.notes}
                                    defaultValue={client?.notes ?? ''}
                                    step={3}
                                />
                                <CustomFieldInputs
                                    fields={applicableFields.filter(
                                        (field) => field.step === 3,
                                    )}
                                    values={client?.custom_fields}
                                    errors={errors}
                                    nativeRequired
                                    dataStep={3}
                                />
                                <div className="grid min-w-0 gap-2">
                                    <Label htmlFor="client-status">
                                        Status
                                    </Label>
                                    <SearchableSelect
                                        id="client-status"
                                        name="status"
                                        defaultValue={
                                            client?.is_active === false
                                                ? 'inactive'
                                                : 'active'
                                        }
                                        dataStep={3}
                                        options={[
                                            {
                                                value: 'active',
                                                label: 'Active',
                                            },
                                            {
                                                value: 'inactive',
                                                label: 'Inactive',
                                            },
                                        ]}
                                        searchable={false}
                                        error={errors.status}
                                    />
                                </div>
                            </section>
                            <DialogFooter>
                                <div className="flex w-full items-center justify-between gap-3">
                                    <DialogClose asChild>
                                        <Button type="button" variant="outline">
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <div className="flex items-center gap-2">
                                        {currentStep > 1 && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    setCurrentStep(
                                                        (step) => step - 1,
                                                    )
                                                }
                                            >
                                                <ChevronLeft />
                                                Back
                                            </Button>
                                        )}
                                        {currentStep <
                                        clientFormSteps.length ? (
                                            <Button
                                                type="button"
                                                formNoValidate
                                                onClick={(event) => {
                                                    event.preventDefault();
                                                    setCurrentStep((step) =>
                                                        Math.min(
                                                            clientFormSteps.length,
                                                            step + 1,
                                                        ),
                                                    );
                                                }}
                                            >
                                                Continue
                                                <ChevronRight />
                                            </Button>
                                        ) : (
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing && <Spinner />}
                                                {isEditing
                                                    ? 'Save changes'
                                                    : 'Create client'}
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function StepHeading({ step }: { step: 1 | 2 | 3 }) {
    const definition = clientFormSteps[step - 1];

    return (
        <div>
            <h3 className="font-medium">{definition.title}</h3>
            <p className="mt-1 text-sm text-muted-foreground">
                {definition.description}
            </p>
        </div>
    );
}

function TextField({
    label,
    name,
    type = 'text',
    error,
    defaultValue,
    placeholder,
    required = false,
    step,
}: {
    label: string;
    name: string;
    type?: string;
    error?: string;
    defaultValue?: string;
    placeholder?: string;
    required?: boolean;
    step?: 1 | 2 | 3;
}) {
    return (
        <div className="grid min-w-0 gap-2">
            <Label htmlFor={`client-${name}`}>{label}</Label>
            <Input
                id={`client-${name}`}
                name={name}
                type={type}
                defaultValue={defaultValue}
                placeholder={placeholder}
                required={required}
                data-client-step={step}
            />
            <InputError message={error} />
        </div>
    );
}

function TextAreaField({
    label,
    name,
    error,
    defaultValue,
    step,
}: {
    label: string;
    name: string;
    error?: string;
    defaultValue: string;
    step?: 1 | 2 | 3;
}) {
    return (
        <div className="grid min-w-0 gap-2">
            <Label htmlFor={`client-${name}`}>{label}</Label>
            <textarea
                id={`client-${name}`}
                name={name}
                defaultValue={defaultValue}
                data-client-step={step}
                className="min-h-20 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
            />
            <InputError message={error} />
        </div>
    );
}
