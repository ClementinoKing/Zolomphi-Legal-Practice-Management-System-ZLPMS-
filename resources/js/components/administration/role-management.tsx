import { Form } from '@inertiajs/react';
import { Plus, ShieldCheck } from 'lucide-react';
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
    permissions: Pick<Permission, 'key' | 'name' | 'group'>[];
};

export default function RoleManagement({
    roles,
    permissions,
}: {
    roles: Role[];
    permissions: Permission[];
}) {
    return (
        <Card>
            <CardHeader className="gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="grid gap-1">
                    <CardTitle>Roles</CardTitle>
                    <CardDescription>
                        {roles.length} role{roles.length === 1 ? '' : 's'}{' '}
                        bundle access for your team.
                    </CardDescription>
                </div>
                <CreateRoleDialog permissions={permissions} />
            </CardHeader>
            <CardContent>
                <div className="grid gap-3 lg:grid-cols-2">
                    {roles.length === 0 ? (
                        <div className="rounded-lg border border-dashed px-4 py-10 text-center text-sm text-muted-foreground lg:col-span-2">
                            <ShieldCheck className="mx-auto mb-3 size-5" />
                            No roles have been configured yet.
                        </div>
                    ) : (
                        roles.map((role) => (
                            <div
                                key={role.id}
                                className="rounded-lg border p-4"
                            >
                                <div className="flex items-center justify-between gap-3">
                                    <p className="font-medium">{role.name}</p>
                                    <Badge variant="secondary">
                                        {role.user_count} users
                                    </Badge>
                                </div>
                                {role.description && (
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {role.description}
                                    </p>
                                )}
                                <div className="mt-3 flex flex-wrap gap-1">
                                    {role.permissions.length === 0 ? (
                                        <span className="text-sm text-muted-foreground">
                                            No permissions assigned
                                        </span>
                                    ) : (
                                        role.permissions.map((permission) => (
                                            <Badge
                                                key={permission.key}
                                                variant="outline"
                                            >
                                                {permission.key}
                                            </Badge>
                                        ))
                                    )}
                                </div>
                            </div>
                        ))
                    )}
                </div>
            </CardContent>
        </Card>
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
}: {
    label: string;
    name: string;
    error?: string;
    required?: boolean;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={`role-${name}`}>{label}</Label>
            <Input id={`role-${name}`} name={name} required={required} />
            <InputError message={error} />
        </div>
    );
}

function PermissionCheckboxes({
    permissions,
    error,
}: {
    permissions: Permission[];
    error?: string;
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
