import { Form } from '@inertiajs/react';
import {
    ArrowDownUp,
    Building2,
    Pencil,
    Plus,
    Search,
    UserRound,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import ClientController from '@/actions/App/Http/Controllers/ClientController';
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
};

type SortField = 'name' | 'client_type' | 'code';

const pageSize = 10;

export default function ClientManagement({ clients }: { clients: Client[] }) {
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
        <Card>
            <CardHeader className="gap-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="grid gap-1">
                        <CardTitle>Clients</CardTitle>
                        <CardDescription>
                            Manage the people and organizations your firm
                            represents.
                        </CardDescription>
                    </div>
                    <ClientDialog />
                </div>
                <div className="grid gap-3 md:grid-cols-[minmax(0,1fr)_11rem_9rem]">
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            aria-label="Search clients"
                            className="pl-9"
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
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
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
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
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
            </CardHeader>
            <CardContent>
                <div className="overflow-x-auto rounded-lg border">
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
                                            <ClientDialog client={client} />
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
            </CardContent>
        </Card>
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

function ClientDialog({ client }: { client?: Client }) {
    const [isOpen, setIsOpen] = useState(false);
    const isEditing = client !== undefined;

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
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
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-x-hidden overflow-y-auto sm:max-w-2xl">
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
                <Form
                    key={client?.id ?? 'new-client'}
                    {...(client
                        ? ClientController.update.form(client)
                        : ClientController.store.form())}
                    resetOnSuccess
                    className="grid min-w-0 gap-5"
                    onSuccess={() => setIsOpen(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid min-w-0 gap-4 sm:grid-cols-2">
                                <SelectField
                                    label="Client type"
                                    name="type"
                                    error={errors.type}
                                    defaultValue={
                                        client?.client_type ?? 'organization'
                                    }
                                >
                                    <option value="organization">
                                        Organization
                                    </option>
                                    <option value="individual">
                                        Individual
                                    </option>
                                </SelectField>
                                <TextField
                                    label="Client or organization name"
                                    name="name"
                                    error={errors.name}
                                    defaultValue={client?.name}
                                    placeholder="Chisomo Holdings Limited"
                                    required
                                />
                                <TextField
                                    label="Primary contact"
                                    name="contact_person"
                                    error={errors.contact_person}
                                    defaultValue={client?.contact_person ?? ''}
                                    placeholder="Annie Chisomo"
                                />
                                <TextField
                                    label="Email address"
                                    name="email"
                                    type="email"
                                    error={errors.email}
                                    defaultValue={client?.email ?? ''}
                                    placeholder="legal@example.com"
                                />
                                <TextField
                                    label="Phone number"
                                    name="phone"
                                    error={errors.phone}
                                    defaultValue={client?.phone ?? ''}
                                    placeholder="+265 999 000 000"
                                />
                                <TextField
                                    label="Alternate phone"
                                    name="alternate_phone"
                                    error={errors.alternate_phone}
                                    defaultValue={client?.alternate_phone ?? ''}
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
                            <div className="grid min-w-0 gap-4 sm:grid-cols-2">
                                <TextField
                                    label="Tax number"
                                    name="tax_number"
                                    error={errors.tax_number}
                                    defaultValue={client?.tax_number ?? ''}
                                />
                                <TextField
                                    label="Registration number"
                                    name="registration_number"
                                    error={errors.registration_number}
                                    defaultValue={
                                        client?.registration_number ?? ''
                                    }
                                />
                                <TextField
                                    label="Address line 1"
                                    name="address_line1"
                                    error={errors.address_line1}
                                    defaultValue={client?.address_line1 ?? ''}
                                />
                                <TextField
                                    label="Address line 2"
                                    name="address_line2"
                                    error={errors.address_line2}
                                    defaultValue={client?.address_line2 ?? ''}
                                />
                                <TextField
                                    label="City"
                                    name="city"
                                    error={errors.city}
                                    defaultValue={client?.city ?? ''}
                                />
                                <TextField
                                    label="Country"
                                    name="country"
                                    error={errors.country}
                                    defaultValue={client?.country ?? ''}
                                />
                            </div>
                            <TextAreaField
                                label="Internal notes"
                                name="notes"
                                error={errors.notes}
                                defaultValue={client?.notes ?? ''}
                            />
                            <div className="grid min-w-0 gap-2">
                                <Label htmlFor="client-status">Status</Label>
                                <select
                                    id="client-status"
                                    name="status"
                                    defaultValue={
                                        client?.is_active === false
                                            ? 'inactive'
                                            : 'active'
                                    }
                                    className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                                >
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                                <InputError message={errors.status} />
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {isEditing
                                        ? 'Save changes'
                                        : 'Create client'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
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
}: {
    label: string;
    name: string;
    type?: string;
    error?: string;
    defaultValue?: string;
    placeholder?: string;
    required?: boolean;
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
            />
            <InputError message={error} />
        </div>
    );
}

function SelectField({
    label,
    name,
    error,
    defaultValue,
    children,
}: {
    label: string;
    name: string;
    error?: string;
    defaultValue: Client['client_type'];
    children: React.ReactNode;
}) {
    return (
        <div className="grid min-w-0 gap-2">
            <Label htmlFor={`client-${name}`}>{label}</Label>
            <select
                id={`client-${name}`}
                name={name}
                defaultValue={defaultValue}
                className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
            >
                {children}
            </select>
            <InputError message={error} />
        </div>
    );
}

function TextAreaField({
    label,
    name,
    error,
    defaultValue,
}: {
    label: string;
    name: string;
    error?: string;
    defaultValue: string;
}) {
    return (
        <div className="grid min-w-0 gap-2">
            <Label htmlFor={`client-${name}`}>{label}</Label>
            <textarea
                id={`client-${name}`}
                name={name}
                defaultValue={defaultValue}
                className="min-h-20 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
            />
            <InputError message={error} />
        </div>
    );
}
