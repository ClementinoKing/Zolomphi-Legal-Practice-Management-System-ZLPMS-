import { Form } from '@inertiajs/react';
import { Pencil, Plus, UsersRound } from 'lucide-react';
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

type Department = {
    id: number;
    name: string;
    code: string;
    description: string | null;
    is_active: boolean;
    users_count: number;
};

export default function DepartmentManagement({
    departments,
}: {
    departments: Department[];
}) {
    return (
        <Card>
            <CardHeader className="gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="grid gap-1">
                    <CardTitle>Departments</CardTitle>
                    <CardDescription>
                        Maintain the teams and business units that structure the
                        law firm.
                    </CardDescription>
                </div>
                <DepartmentDialog />
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
                                    Department
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
                                    Team members
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
                            {departments.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        <UsersRound className="mx-auto mb-3 size-5" />
                                        No departments have been created yet.
                                    </td>
                                </tr>
                            ) : (
                                departments.map((department) => (
                                    <tr
                                        key={department.id}
                                        className="transition-colors hover:bg-muted/30"
                                    >
                                        <td className="px-4 py-3 align-top">
                                            <p className="font-medium">
                                                {department.name}
                                            </p>
                                            {department.description && (
                                                <p className="mt-0.5 max-w-lg text-muted-foreground">
                                                    {department.description}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 align-top font-mono text-muted-foreground">
                                            {department.code}
                                        </td>
                                        <td className="px-4 py-3 align-top text-muted-foreground">
                                            {department.users_count}
                                        </td>
                                        <td className="px-4 py-3 align-top">
                                            <Badge
                                                variant={
                                                    department.is_active
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {department.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-right align-top">
                                            <DepartmentDialog
                                                department={department}
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

function DepartmentDialog({ department }: { department?: Department }) {
    const isEditing = department !== undefined;

    return (
        <Dialog>
            <DialogTrigger asChild>
                {isEditing ? (
                    <Button size="sm" variant="outline">
                        <Pencil />
                        Edit
                    </Button>
                ) : (
                    <Button>
                        <Plus />
                        Add department
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit department' : 'Add department'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update the department details and availability.'
                            : 'Create a department for organizing your law firm team.'}
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...(department
                        ? AdministrationController.updateDepartment.form(
                              department,
                          )
                        : AdministrationController.storeDepartment.form())}
                    resetOnSuccess
                    className="grid gap-5"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <TextField
                                    label="Department name"
                                    name="name"
                                    error={errors.name}
                                    defaultValue={department?.name}
                                    required
                                />
                                <TextField
                                    label="Code"
                                    name="code"
                                    error={errors.code}
                                    defaultValue={department?.code}
                                    placeholder="legal-services"
                                    required
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="department-description">
                                    Description
                                </Label>
                                <textarea
                                    id="department-description"
                                    name="description"
                                    defaultValue={department?.description ?? ''}
                                    className="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                                />
                                <InputError message={errors.description} />
                            </div>
                            <div className="flex items-center gap-2">
                                <input
                                    type="hidden"
                                    name="is_active"
                                    value="0"
                                />
                                <input
                                    id="department-is-active"
                                    className="size-4 accent-primary"
                                    type="checkbox"
                                    name="is_active"
                                    value="1"
                                    defaultChecked={
                                        department?.is_active ?? true
                                    }
                                />
                                <Label htmlFor="department-is-active">
                                    Department is active
                                </Label>
                                <InputError message={errors.is_active} />
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
                                        : 'Create department'}
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
    defaultValue,
    placeholder,
    required = false,
}: {
    label: string;
    name: string;
    error?: string;
    defaultValue?: string;
    placeholder?: string;
    required?: boolean;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={`department-${name}`}>{label}</Label>
            <Input
                id={`department-${name}`}
                name={name}
                defaultValue={defaultValue}
                placeholder={placeholder}
                required={required}
            />
            <InputError message={error} />
        </div>
    );
}
