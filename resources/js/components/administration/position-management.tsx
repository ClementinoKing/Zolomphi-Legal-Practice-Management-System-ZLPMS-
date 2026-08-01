import { Form } from '@inertiajs/react';
import { BriefcaseBusiness, Pencil, Plus } from 'lucide-react';
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
};

type Position = {
    id: number;
    department_id: number;
    name: string;
    code: string;
    description: string | null;
    is_active: boolean;
    department: Department;
};

type Props = {
    positions: Position[];
    departments: Department[];
};

export default function PositionManagement({ positions, departments }: Props) {
    return (
        <Card>
            <CardHeader className="gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="grid gap-1">
                    <CardTitle>Positions</CardTitle>
                    <CardDescription>
                        Define the roles available within each department of
                        your law firm.
                    </CardDescription>
                </div>
                <PositionDialog departments={departments} />
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
                                    Position
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
                                    Code
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
                            {positions.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        <BriefcaseBusiness className="mx-auto mb-3 size-5" />
                                        No positions have been created yet.
                                    </td>
                                </tr>
                            ) : (
                                positions.map((position) => (
                                    <tr
                                        key={position.id}
                                        className="transition-colors hover:bg-muted/30"
                                    >
                                        <td className="px-4 py-3 align-top">
                                            <p className="font-medium">
                                                {position.name}
                                            </p>
                                            {position.description && (
                                                <p className="mt-0.5 max-w-lg text-muted-foreground">
                                                    {position.description}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 align-top text-muted-foreground">
                                            {position.department.name}
                                        </td>
                                        <td className="px-4 py-3 align-top font-mono text-muted-foreground">
                                            {position.code}
                                        </td>
                                        <td className="px-4 py-3 align-top">
                                            <Badge
                                                variant={
                                                    position.is_active
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {position.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-right align-top">
                                            <PositionDialog
                                                position={position}
                                                departments={departments}
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

function PositionDialog({
    position,
    departments,
}: {
    position?: Position;
    departments: Department[];
}) {
    const isEditing = position !== undefined;

    return (
        <Dialog>
            <DialogTrigger asChild>
                {isEditing ? (
                    <Button size="sm" variant="outline">
                        <Pencil />
                        Edit
                    </Button>
                ) : (
                    <Button disabled={departments.length === 0}>
                        <Plus />
                        Add position
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit position' : 'Add position'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update the position details and department assignment.'
                            : 'Create a position within one of your law firm departments.'}
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...(position
                        ? AdministrationController.updatePosition.form(position)
                        : AdministrationController.storePosition.form())}
                    resetOnSuccess
                    className="grid gap-5"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="position-department">
                                        Department
                                    </Label>
                                    <select
                                        id="position-department"
                                        name="department_id"
                                        defaultValue={position?.department_id}
                                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                                        required
                                    >
                                        <option value="">
                                            Select department
                                        </option>
                                        {departments.map((department) => (
                                            <option
                                                key={department.id}
                                                value={department.id}
                                            >
                                                {department.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={errors.department_id}
                                    />
                                </div>
                                <TextField
                                    label="Position title"
                                    name="name"
                                    error={errors.name}
                                    defaultValue={position?.name}
                                    placeholder="Senior Associate"
                                    required
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="position-description">
                                    Description
                                </Label>
                                <textarea
                                    id="position-description"
                                    name="description"
                                    defaultValue={position?.description ?? ''}
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
                                    id="position-is-active"
                                    className="size-4 accent-primary"
                                    type="checkbox"
                                    name="is_active"
                                    value="1"
                                    defaultChecked={position?.is_active ?? true}
                                />
                                <Label htmlFor="position-is-active">
                                    Position is active
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
                                        : 'Create position'}
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
            <Label htmlFor={`position-${name}`}>{label}</Label>
            <Input
                id={`position-${name}`}
                name={name}
                defaultValue={defaultValue}
                placeholder={placeholder}
                required={required}
            />
            <InputError message={error} />
        </div>
    );
}
