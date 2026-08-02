import { Form } from '@inertiajs/react';
import { Pencil, Plus, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import AdministrationController from '@/actions/App/Http/Controllers/AdministrationController';
import InputError from '@/components/input-error';
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

type Permission = {
    id: number;
    key: string;
    name: string;
    group: string;
    description: string | null;
};

type Role = {
    id: number;
    name: string;
    description: string | null;
    user_count: number;
    permission_ids: number[];
    permissions: Pick<Permission, 'key' | 'name' | 'group'>[];
};

export default function RoleManagement({
    roles,
    permissions,
    canManageRoles,
}: {
    roles: Role[];
    permissions: Permission[];
    canManageRoles: boolean;
}) {
    return (
        <section className="grid gap-5">
            <header className="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-start sm:justify-between">
                <div className="grid gap-1">
                    <p className="text-sm text-muted-foreground">
                        {roles.length} role{roles.length === 1 ? '' : 's'}{' '}
                        bundle access for your team.
                    </p>
                </div>
                {canManageRoles && <CreateRoleDialog permissions={permissions} />}
            </header>
            <div className="overflow-x-auto rounded-xl border border-border/70 bg-background">
                <table className="w-full min-w-200 text-left text-sm">
                    <thead className="bg-muted/50 text-xs tracking-wide text-muted-foreground uppercase">
                        <tr>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Role
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Permissions
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Assigned users
                            </th>
                            {canManageRoles && (
                                <th
                                    scope="col"
                                    className="px-4 py-3 text-right font-medium"
                                >
                                    Actions
                                </th>
                            )}
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {roles.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={canManageRoles ? 4 : 3}
                                    className="px-4 py-12 text-center text-muted-foreground"
                                >
                                    <ShieldCheck className="mx-auto mb-3 size-5" />
                                    No roles have been configured yet.
                                </td>
                            </tr>
                        ) : (
                            roles.map((role) => (
                                <tr
                                    key={role.id}
                                    className="transition-colors hover:bg-muted/30"
                                >
                                    <td className="px-4 py-3 align-top">
                                        <p className="font-medium">
                                            {role.name}
                                        </p>
                                        {role.description && (
                                            <p className="mt-0.5 max-w-lg text-muted-foreground">
                                                {role.description}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 align-top">
                                        <div className="flex flex-wrap gap-1">
                                            {role.permissions.length === 0 ? (
                                                <span className="text-sm text-muted-foreground">
                                                    No permissions assigned
                                                </span>
                                            ) : (
                                                role.permissions.map(
                                                    (permission) => (
                                                        <Badge
                                                            key={permission.key}
                                                            variant="outline"
                                                        >
                                                            {permission.key}
                                                        </Badge>
                                                    ),
                                                )
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 align-top">
                                        <Badge variant="secondary">
                                            {role.user_count} users
                                        </Badge>
                                    </td>
                                    {canManageRoles && (
                                        <td className="px-4 py-3 text-right align-top">
                                            <EditRoleDialog
                                                role={role}
                                                permissions={permissions}
                                            />
                                        </td>
                                    )}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function EditRoleDialog({
    role,
    permissions,
}: {
    role: Role;
    permissions: Permission[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <Pencil />
                    Edit
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Edit role</DialogTitle>
                    <DialogDescription>
                        Update this role’s details and permission assignments.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...AdministrationController.updateRole.form(role)}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-5"
                >
                    {({ errors, processing }) => (
                        <>
                            <TextField
                                label="Role name"
                                name="name"
                                defaultValue={role.name}
                                error={errors.name}
                                required
                            />
                            <TextField
                                label="Description"
                                name="description"
                                defaultValue={role.description ?? ''}
                                error={errors.description}
                            />
                            <PermissionCheckboxes
                                permissions={permissions}
                                selectedPermissionIds={role.permission_ids}
                                error={errors.permission_ids}
                            />
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Save changes
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function CreateRoleDialog({ permissions }: { permissions: Permission[] }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button>
                    <Plus />
                    Create role
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Create role</DialogTitle>
                    <DialogDescription>
                        Bundle permissions into a clear, auditable access role.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...AdministrationController.storeRole.form()}
                    resetOnSuccess
                    className="grid gap-5"
                >
                    {({ errors, processing }) => (
                        <>
                            <TextField
                                label="Role name"
                                name="name"
                                error={errors.name}
                                required
                            />
                            <TextField
                                label="Description"
                                name="description"
                                error={errors.description}
                            />
                            <PermissionCheckboxes
                                permissions={permissions}
                                error={errors.permission_ids}
                            />
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Create role
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
    defaultValue,
}: {
    label: string;
    name: string;
    error?: string;
    required?: boolean;
    defaultValue?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={`role-${name}`}>{label}</Label>
            <Input
                id={`role-${name}`}
                name={name}
                required={required}
                defaultValue={defaultValue}
            />
            <InputError message={error} />
        </div>
    );
}

function PermissionCheckboxes({
    permissions,
    error,
    selectedPermissionIds = [],
}: {
    permissions: Permission[];
    error?: string;
    selectedPermissionIds?: number[];
}) {
    return (
        <div className="grid gap-2">
            <Label>Permissions</Label>
            <div className="grid max-h-72 gap-2 overflow-y-auto rounded-lg border p-3 sm:grid-cols-2">
                {permissions.map((permission) => (
                    <label
                        key={permission.id}
                        className="flex items-start gap-2 text-sm"
                    >
                        <input
                            className="mt-0.5 size-4 accent-primary"
                            type="checkbox"
                            name="permission_ids[]"
                            value={permission.id}
                            defaultChecked={selectedPermissionIds.includes(
                                permission.id,
                            )}
                        />
                        <span>
                            <span className="block font-medium">
                                {permission.name}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {permission.key}
                            </span>
                        </span>
                    </label>
                ))}
            </div>
            <InputError message={error} />
        </div>
    );
}
