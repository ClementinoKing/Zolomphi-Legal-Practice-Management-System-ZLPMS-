import { Form } from '@inertiajs/react';
import { Pencil, Plus, UsersRound } from 'lucide-react';
import { useState } from 'react';
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
    return (
        <Card>
            <CardHeader className="gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="grid gap-1">
                    <CardTitle>Users</CardTitle>
                    <CardDescription>
                        {users.length} user{users.length === 1 ? '' : 's'} can
                        access the practice workspace.
                    </CardDescription>
                </div>
                <UserDialog
                    roles={roles}
                    departments={departments}
                    positions={positions}
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
                                    User
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Department
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Position
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Roles
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
                            {users.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        <UsersRound className="mx-auto mb-3 size-5" />
                                        No users have been added yet.
                                    </td>
                                </tr>
                            ) : (
                                users.map((user) => (
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
            </CardContent>
        </Card>
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

    function handleOpenChange(open: boolean): void {
        if (open) {
            setDepartmentId(initialDepartmentId);
            setPositionId(initialPositionId);
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
                            : 'Send a secure password setup link and assign the right access for the new team member.'}
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
                    onSuccess={() => {
                        if (isEditing) {
                            setIsOpen(false);
                        }
                    }}
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
                                    {isEditing
                                        ? 'Save changes'
                                        : 'Create and invite user'}
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
