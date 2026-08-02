import { Form } from '@inertiajs/react';
import { KeyRound, Plus } from 'lucide-react';
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

export default function PermissionManagement({
    permissions,
}: {
    permissions: Permission[];
}) {
    return (
        <section className="grid gap-5">
            <header className="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-start sm:justify-between">
                <div className="grid gap-1">
                    <p className="text-sm text-muted-foreground">
                        {permissions.length} permission
                        {permissions.length === 1 ? '' : 's'} define the actions
                        available in the system.
                    </p>
                </div>
                <CreatePermissionDialog />
            </header>
            {permissions.length === 0 ? (
                <div className="rounded-xl border border-dashed px-4 py-10 text-center text-sm text-muted-foreground">
                    <KeyRound className="mx-auto mb-3 size-5" />
                    No permissions have been configured yet.
                </div>
            ) : (
                <div className="overflow-x-auto rounded-xl border border-border/70 bg-background">
                    <table className="w-full min-w-140 text-left text-sm">
                        <thead className="bg-muted/50 text-xs tracking-wide text-muted-foreground uppercase">
                            <tr>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Permission
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Group
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {permissions.map((permission) => (
                                <tr
                                    key={permission.id}
                                    className="transition-colors hover:bg-muted/30"
                                >
                                    <td className="px-4 py-3 align-top">
                                        <p className="font-medium">
                                            {permission.name}
                                        </p>
                                        <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                                            {permission.key}
                                        </p>
                                        {permission.description && (
                                            <p className="mt-1 text-muted-foreground">
                                                {permission.description}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 align-top">
                                        <Badge variant="outline">
                                            {permission.group}
                                        </Badge>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}

function CreatePermissionDialog() {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button>
                    <Plus />
                    Create permission
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Create permission</DialogTitle>
                    <DialogDescription>
                        Use a stable dot-separated key, such as{' '}
                        <code>reports.view</code>.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...AdministrationController.storePermission.form()}
                    resetOnSuccess
                    className="grid gap-5"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <TextField
                                    label="Display name"
                                    name="name"
                                    error={errors.name}
                                    required
                                />
                                <TextField
                                    label="Permission key"
                                    name="key"
                                    error={errors.key}
                                    placeholder="reports.view"
                                    required
                                />
                                <TextField
                                    label="Group"
                                    name="group"
                                    error={errors.group}
                                    placeholder="Reports"
                                    required
                                />
                                <TextField
                                    label="Description"
                                    name="description"
                                    error={errors.description}
                                />
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Create permission
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
    placeholder,
}: {
    label: string;
    name: string;
    error?: string;
    required?: boolean;
    placeholder?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={`permission-${name}`}>{label}</Label>
            <Input
                id={`permission-${name}`}
                name={name}
                required={required}
                placeholder={placeholder}
            />
            <InputError message={error} />
        </div>
    );
}
