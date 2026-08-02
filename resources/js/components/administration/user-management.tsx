import { Form } from '@inertiajs/react';
import { Pencil, Plus, Search, UsersRound, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import AdministrationController from '@/actions/App/Http/Controllers/AdministrationController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import PasswordStrength from '@/components/password-strength';
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

type Role = {
    id: number;
    name: string;
};

type Department = {
    id: number;
    name: string;
};

type Position = {
    id: number;
    department_id: number;
    name: string;
};

type User = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    department_id: number | null;
    department: string | null;
    position_id: number | null;
    position: string | null;
    status: string;
    role_ids: number[];
    roles: string[];
};

const pageSize = 10;
const defaultTemporaryPassword = '12345678';

export default function UserManagement({
    users,
    roles,
    departments,
    positions,
}: {
    users: User[];
    roles: Role[];
    departments: Department[];
    positions: Position[];
}) {
    const [search, setSearch] = useState('');
    const [departmentFilter, setDepartmentFilter] = useState('all');
    const [positionFilter, setPositionFilter] = useState('all');
    const [roleFilter, setRoleFilter] = useState('all');
    const [statusFilter, setStatusFilter] = useState('all');
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('asc');
    const [page, setPage] = useState(1);

    const filteredUsers = useMemo(() => {
        const searchTerm = search.trim().toLocaleLowerCase();

        return users
            .filter((user) => {
                const matchesSearch =
                    searchTerm === '' ||
                    [
                        user.name,
                        user.email,
                        user.phone,
                        user.department,
                        user.position,
                        ...user.roles,
                    ].some((value) =>
                        value?.toLocaleLowerCase().includes(searchTerm),
                    );
                const matchesDepartment =
                    departmentFilter === 'all' ||
                    user.department_id === Number(departmentFilter);
                const matchesPosition =
                    positionFilter === 'all' ||
                    user.position_id === Number(positionFilter);
                const matchesRole =
                    roleFilter === 'all' ||
                    (roleFilter === 'unassigned'
                        ? user.role_ids.length === 0
                        : user.role_ids.includes(Number(roleFilter)));
                const matchesStatus =
                    statusFilter === 'all' || user.status === statusFilter;

                return (
                    matchesSearch &&
                    matchesDepartment &&
                    matchesPosition &&
                    matchesRole &&
                    matchesStatus
                );
            })
            .sort((first, second) => {
                const comparison = first.name.localeCompare(second.name);

                return sortDirection === 'asc' ? comparison : -comparison;
            });
    }, [
        departmentFilter,
        positionFilter,
        roleFilter,
        search,
        sortDirection,
        statusFilter,
        users,
    ]);

    const availablePositions = positions.filter(
        (position) =>
            departmentFilter === 'all' ||
            position.department_id === Number(departmentFilter),
    );
    const totalPages = Math.max(1, Math.ceil(filteredUsers.length / pageSize));
    const currentPage = Math.min(page, totalPages);
    const visibleUsers = filteredUsers.slice(
        (currentPage - 1) * pageSize,
        currentPage * pageSize,
    );
    const hasActiveFilters =
        search !== '' ||
        departmentFilter !== 'all' ||
        positionFilter !== 'all' ||
        roleFilter !== 'all' ||
        statusFilter !== 'all';

    function resetPage(): void {
        setPage(1);
    }

    function clearFilters(): void {
        setSearch('');
        setDepartmentFilter('all');
        setPositionFilter('all');
        setRoleFilter('all');
        setStatusFilter('all');
        resetPage();
    }

    return (
        <section className="grid gap-5">
            <header className="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-start sm:justify-between">
                <div className="grid gap-1">
                    <p className="text-sm text-muted-foreground">
                        {filteredUsers.length} of {users.length} user
                        {users.length === 1 ? '' : 's'} in the practice
                        workspace.
                    </p>
                </div>
                <UserDialog
                    roles={roles}
                    departments={departments}
                    positions={positions}
                />
            </header>
            <div className="grid gap-3 rounded-xl bg-muted/35 p-3 lg:grid-cols-[minmax(0,1fr)_12rem_12rem_11rem_9rem_auto]">
                <div className="relative min-w-0">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        aria-label="Search users"
                        className="bg-background pl-9"
                        placeholder="Search name, email, department, or role"
                        value={search}
                        onChange={(event) => {
                            setSearch(event.target.value);
                            resetPage();
                        }}
                    />
                </div>
                <DirectoryFilter
                    ariaLabel="Filter users by department"
                    value={departmentFilter}
                    onChange={(value) => {
                        setDepartmentFilter(value);
                        setPositionFilter('all');
                        resetPage();
                    }}
                >
                    <option value="all">All departments</option>
                    {departments.map((department) => (
                        <option key={department.id} value={department.id}>
                            {department.name}
                        </option>
                    ))}
                </DirectoryFilter>
                <DirectoryFilter
                    ariaLabel="Filter users by position"
                    value={positionFilter}
                    onChange={(value) => {
                        setPositionFilter(value);
                        resetPage();
                    }}
                >
                    <option value="all">All positions</option>
                    {availablePositions.map((position) => (
                        <option key={position.id} value={position.id}>
                            {position.name}
                        </option>
                    ))}
                </DirectoryFilter>
                <DirectoryFilter
                    ariaLabel="Filter users by role"
                    value={roleFilter}
                    onChange={(value) => {
                        setRoleFilter(value);
                        resetPage();
                    }}
                >
                    <option value="all">All roles</option>
                    <option value="unassigned">No role assigned</option>
                    {roles.map((role) => (
                        <option key={role.id} value={role.id}>
                            {role.name}
                        </option>
                    ))}
                </DirectoryFilter>
                <DirectoryFilter
                    ariaLabel="Filter users by status"
                    value={statusFilter}
                    onChange={(value) => {
                        setStatusFilter(value);
                        resetPage();
                    }}
                >
                    <option value="all">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </DirectoryFilter>
                {hasActiveFilters ? (
                    <Button
                        type="button"
                        variant="ghost"
                        className="justify-self-start lg:justify-self-end"
                        onClick={clearFilters}
                    >
                        <X />
                        Clear
                    </Button>
                ) : (
                    <span className="hidden lg:block" />
                )}
            </div>
            <div className="overflow-x-auto rounded-xl border border-border/70 bg-background">
                <table className="w-full min-w-200 text-left text-sm">
                    <thead className="bg-muted/50 text-xs tracking-wide text-muted-foreground uppercase">
                        <tr>
                            <th scope="col" className="px-4 py-3 font-medium">
                                <button
                                    type="button"
                                    className="inline-flex items-center gap-1 hover:text-foreground"
                                    onClick={() => {
                                        setSortDirection((current) =>
                                            current === 'asc' ? 'desc' : 'asc',
                                        );
                                        resetPage();
                                    }}
                                >
                                    User {sortDirection === 'asc' ? '↑' : '↓'}
                                </button>
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Department
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Position
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Roles
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
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
                        {visibleUsers.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={6}
                                    className="px-4 py-12 text-center text-muted-foreground"
                                >
                                    <UsersRound className="mx-auto mb-3 size-5" />
                                    No users match the selected filters.
                                </td>
                            </tr>
                        ) : (
                            visibleUsers.map((user) => (
                                <tr
                                    key={user.id}
                                    className="transition-colors hover:bg-muted/30"
                                >
                                    <td className="px-4 py-3 align-top">
                                        <p className="font-medium">
                                            {user.name}
                                        </p>
                                        <p className="mt-0.5 text-muted-foreground">
                                            {user.email}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3 align-top text-muted-foreground">
                                        {user.department ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 align-top text-muted-foreground">
                                        {user.position ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 align-top">
                                        <div className="flex flex-wrap gap-1">
                                            {user.roles.length === 0 ? (
                                                <span className="text-muted-foreground">
                                                    No role assigned
                                                </span>
                                            ) : (
                                                user.roles.map((role) => (
                                                    <Badge
                                                        key={role}
                                                        variant="outline"
                                                    >
                                                        {role}
                                                    </Badge>
                                                ))
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 align-top">
                                        <Badge
                                            variant={
                                                user.status === 'active'
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {user.status}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-right align-top">
                                        <UserDialog
                                            user={user}
                                            roles={roles}
                                            departments={departments}
                                            positions={positions}
                                        />
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
            <footer className="flex flex-col gap-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                <p>
                    {filteredUsers.length === 0
                        ? 'No users found'
                        : `Showing ${(currentPage - 1) * pageSize + 1}–${Math.min(currentPage * pageSize, filteredUsers.length)} of ${filteredUsers.length} users`}
                </p>
                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        disabled={currentPage === 1}
                        onClick={() => setPage((current) => current - 1)}
                    >
                        Previous
                    </Button>
                    <span>
                        Page {currentPage} of {totalPages}
                    </span>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        disabled={currentPage === totalPages}
                        onClick={() => setPage((current) => current + 1)}
                    >
                        Next
                    </Button>
                </div>
            </footer>
        </section>
    );
}

function DirectoryFilter({
    ariaLabel,
    value,
    onChange,
    children,
}: {
    ariaLabel: string;
    value: string;
    onChange: (value: string) => void;
    children: React.ReactNode;
}) {
    return (
        <select
            aria-label={ariaLabel}
            className="h-9 min-w-0 rounded-md border border-input bg-background px-3 text-sm shadow-xs"
            value={value}
            onChange={(event) => onChange(event.target.value)}
        >
            {children}
        </select>
    );
}

function UserDialog({
    user,
    roles,
    departments,
    positions,
}: {
    user?: User;
    roles: Role[];
    departments: Department[];
    positions: Position[];
}) {
    const isEditing = user !== undefined;
    const initialDepartmentId =
        user?.department_id === null || user?.department_id === undefined
            ? ''
            : String(user.department_id);
    const initialPositionId =
        user?.position_id === null || user?.position_id === undefined
            ? ''
            : String(user.position_id);
    const [isOpen, setIsOpen] = useState(false);
    const [departmentId, setDepartmentId] = useState(initialDepartmentId);
    const [positionId, setPositionId] = useState(initialPositionId);
    const [password, setPassword] = useState(defaultTemporaryPassword);
    const [passwordConfirmation, setPasswordConfirmation] = useState(
        defaultTemporaryPassword,
    );

    function handleOpenChange(open: boolean): void {
        if (open) {
            setDepartmentId(initialDepartmentId);
            setPositionId(initialPositionId);

            if (!isEditing) {
                setPassword(defaultTemporaryPassword);
                setPasswordConfirmation(defaultTemporaryPassword);
            }
        }

        setIsOpen(open);
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
                        Add user
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] w-[calc(100%-2rem)] overflow-x-hidden overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit user' : 'Add user'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update the team member details, position, and access.'
                            : 'Set a temporary password and assign the right access for the new team member.'}
                    </DialogDescription>
                </DialogHeader>
                <Form
                    key={[
                        user?.id ?? 'new',
                        user?.name ?? '',
                        user?.email ?? '',
                        user?.phone ?? '',
                        user?.department_id ?? '',
                        user?.position_id ?? '',
                        user?.status ?? '',
                        user?.role_ids.join('-') ?? '',
                    ].join(':')}
                    {...(user
                        ? AdministrationController.updateUser.form(user)
                        : AdministrationController.storeUser.form())}
                    resetOnSuccess
                    onSuccess={() => setIsOpen(false)}
                    className="grid min-w-0 gap-5"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid min-w-0 gap-4 sm:grid-cols-2">
                                <TextField
                                    label="Full name"
                                    name="name"
                                    error={errors.name}
                                    defaultValue={user?.name}
                                    required
                                />
                                <TextField
                                    label="Email address"
                                    name="email"
                                    type="email"
                                    error={errors.email}
                                    defaultValue={user?.email}
                                    required
                                />
                                <TextField
                                    label="Phone"
                                    name="phone"
                                    error={errors.phone}
                                    defaultValue={user?.phone ?? undefined}
                                />
                                <DepartmentSelect
                                    departments={departments}
                                    departmentId={departmentId}
                                    error={errors.department_id}
                                    onChange={(value) => {
                                        setDepartmentId(value);
                                        setPositionId('');
                                    }}
                                />
                            </div>
                            <div className="grid min-w-0 gap-4 sm:grid-cols-2">
                                <PositionSelect
                                    departmentId={departmentId}
                                    positionId={positionId}
                                    positions={positions}
                                    error={errors.position_id}
                                    onChange={setPositionId}
                                />
                                <div className="grid min-w-0 gap-2">
                                    <Label htmlFor="new-user-status">
                                        Status
                                    </Label>
                                    <select
                                        id="new-user-status"
                                        name="status"
                                        defaultValue={user?.status ?? 'active'}
                                        className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                                    >
                                        <option value="active">Active</option>
                                        <option value="inactive">
                                            Inactive
                                        </option>
                                    </select>
                                    <InputError message={errors.status} />
                                </div>
                            </div>
                            {!isEditing && (
                                <div className="grid min-w-0 gap-4 sm:grid-cols-2">
                                    <div className="grid min-w-0 gap-2">
                                        <Label htmlFor="new-user-password">
                                            Temporary password
                                        </Label>
                                        <PasswordInput
                                            id="new-user-password"
                                            name="password"
                                            value={password}
                                            onChange={(event) =>
                                                setPassword(event.target.value)
                                            }
                                            autoComplete="new-password"
                                            minLength={8}
                                            required
                                        />
                                        <PasswordStrength password={password} />
                                        <InputError message={errors.password} />
                                    </div>
                                    <div className="grid min-w-0 gap-2">
                                        <Label htmlFor="new-user-password-confirmation">
                                            Confirm temporary password
                                        </Label>
                                        <PasswordInput
                                            id="new-user-password-confirmation"
                                            name="password_confirmation"
                                            value={passwordConfirmation}
                                            onChange={(event) =>
                                                setPasswordConfirmation(
                                                    event.target.value,
                                                )
                                            }
                                            autoComplete="new-password"
                                            minLength={8}
                                            required
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Defaults to 12345678. The user must
                                            create a new password at first
                                            sign-in.
                                        </p>
                                        <InputError
                                            message={
                                                errors.password_confirmation
                                            }
                                        />
                                    </div>
                                </div>
                            )}
                            <RoleCheckboxes
                                roles={roles}
                                error={errors.role_ids}
                                selectedRoleIds={user?.role_ids ?? []}
                            />
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button disabled={processing}>
                                    {processing && <Spinner />}
                                    {isEditing ? 'Save changes' : 'Create user'}
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
    error,
    required = false,
    type = 'text',
    defaultValue,
}: {
    label: string;
    name: string;
    error?: string;
    required?: boolean;
    type?: string;
    defaultValue?: string;
}) {
    return (
        <div className="grid min-w-0 gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                type={type}
                defaultValue={defaultValue}
                required={required}
            />
            <InputError message={error} />
        </div>
    );
}

function RoleCheckboxes({
    roles,
    error,
    selectedRoleIds,
}: {
    roles: Role[];
    error?: string;
    selectedRoleIds: number[];
}) {
    return (
        <div className="grid min-w-0 gap-2">
            <Label>Roles</Label>
            <div className="grid gap-2 rounded-lg border p-3 sm:grid-cols-2">
                {roles.map((role) => (
                    <label
                        key={role.id}
                        className="flex items-center gap-2 text-sm"
                    >
                        <input
                            className="size-4 accent-primary"
                            type="checkbox"
                            name="role_ids[]"
                            value={role.id}
                            defaultChecked={selectedRoleIds.includes(role.id)}
                        />
                        {role.name}
                    </label>
                ))}
            </div>
            <InputError message={error} />
        </div>
    );
}

function DepartmentSelect({
    departments,
    departmentId,
    error,
    onChange,
}: {
    departments: Department[];
    departmentId: string;
    error?: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="grid min-w-0 gap-2">
            <Label htmlFor="new-user-department">Department</Label>
            <select
                id="new-user-department"
                name="department_id"
                value={departmentId}
                onChange={(event) => onChange(event.target.value)}
                className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
            >
                <option value="">No department</option>
                {departments.map((department) => (
                    <option key={department.id} value={department.id}>
                        {department.name}
                    </option>
                ))}
            </select>
            <InputError message={error} />
        </div>
    );
}

function PositionSelect({
    departmentId,
    positionId,
    positions,
    error,
    onChange,
}: {
    departmentId: string;
    positionId: string;
    positions: Position[];
    error?: string;
    onChange: (value: string) => void;
}) {
    const departmentPositions = positions.filter(
        (position) => position.department_id === Number(departmentId),
    );

    return (
        <div className="grid min-w-0 gap-2">
            <Label htmlFor="new-user-position">Position</Label>
            <select
                id="new-user-position"
                name="position_id"
                value={positionId}
                onChange={(event) => onChange(event.target.value)}
                disabled={departmentId === ''}
                className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs disabled:cursor-not-allowed disabled:opacity-50"
            >
                <option value="">
                    {departmentId === ''
                        ? 'Select a department first'
                        : 'No position selected'}
                </option>
                {departmentPositions.map((position) => (
                    <option key={position.id} value={position.id}>
                        {position.name}
                    </option>
                ))}
            </select>
            <InputError message={error} />
        </div>
    );
}
