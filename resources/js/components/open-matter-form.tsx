import { Form } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Plus,
    Trash2,
    UsersRound,
} from 'lucide-react';
import { Children, isValidElement, useState } from 'react';
import MatterController from '@/actions/App/Http/Controllers/MatterController';
import { CustomFieldInputs } from '@/components/custom-field-inputs';
import InputError from '@/components/input-error';
import SearchableSelect from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { CustomFieldDefinition } from '@/types/custom-fields';

type Client = {
    id: number;
    name: string;
    client_number: string;
};

type ResponsibleUser = {
    id: number;
    name: string;
    email: string;
};

type BillingType = 'hourly' | 'fixed_fee' | 'contingency';

type MatterType = {
    id: number;
    name: string;
    default_billing_type: BillingType;
    default_hourly_rate: string | null;
};

type Participant = {
    id: number;
    role: string;
    type: 'individual' | 'organization';
};

type Props = {
    clients: Client[];
    responsibleUsers: ResponsibleUser[];
    matterTypes: MatterType[];
    today: string;
    customFields: CustomFieldDefinition[];
    onSuccess?: () => void;
};

const steps = ['Matter details', 'Billing and status', 'Participants'];

export default function OpenMatterForm({
    clients,
    responsibleUsers,
    matterTypes,
    today,
    customFields,
    onSuccess,
}: Props) {
    const [step, setStep] = useState(1);
    const [clientId, setClientId] = useState('');
    const [responsibleUserId, setResponsibleUserId] = useState('');
    const [matterTypeId, setMatterTypeId] = useState('');
    const [title, setTitle] = useState('');
    const [billingType, setBillingType] = useState<BillingType>('hourly');
    const [hourlyRate, setHourlyRate] = useState('');
    const [attorneyIds, setAttorneyIds] = useState<number[]>([]);
    const [participants, setParticipants] = useState<Participant[]>([]);
    const [nextParticipantId, setNextParticipantId] = useState(1);

    const canContinue =
        step !== 1 ||
        (clientId !== '' &&
            responsibleUserId !== '' &&
            matterTypeId !== '' &&
            title.trim() !== '');

    function applyMatterType(id: string): void {
        setMatterTypeId(id);

        const matterType = matterTypes.find(
            (record) => record.id === Number(id),
        );

        if (matterType) {
            setBillingType(matterType.default_billing_type);
            setHourlyRate(matterType.default_hourly_rate ?? '');
        }
    }

    function addParticipant(): void {
        setParticipants((current) => [
            ...current,
            {
                id: nextParticipantId,
                role: 'Opposing party',
                type: 'individual',
            },
        ]);
        setNextParticipantId((current) => current + 1);
    }

    function toggleAttorney(attorneyId: number): void {
        setAttorneyIds((current) =>
            current.includes(attorneyId)
                ? current.filter((id) => id !== attorneyId)
                : [...current, attorneyId],
        );
    }

    function resetWizard(): void {
        setStep(1);
        setClientId('');
        setResponsibleUserId('');
        setMatterTypeId('');
        setTitle('');
        setBillingType('hourly');
        setHourlyRate('');
        setAttorneyIds([]);
        setParticipants([]);
        setNextParticipantId(1);
    }

    return (
        <Form
            {...MatterController.store.form()}
            resetOnSuccess
            className="grid min-w-0 gap-6"
            onSubmit={(event) => {
                if (step < steps.length) {
                    event.preventDefault();
                }
            }}
            onSuccess={() => {
                resetWizard();
                onSuccess?.();
            }}
        >
            {({ errors, processing }) => (
                <>
                    <ol className="grid grid-cols-3 gap-2">
                        {steps.map((label, index) => {
                            const stepNumber = index + 1;
                            const isCurrent = stepNumber === step;
                            const isComplete = stepNumber < step;

                            return (
                                <li
                                    key={label}
                                    className="grid gap-1 text-center text-xs"
                                >
                                    <span
                                        className={`mx-auto flex size-7 items-center justify-center rounded-full border font-medium ${isCurrent || isComplete ? 'border-primary bg-primary text-primary-foreground' : 'text-muted-foreground'}`}
                                    >
                                        {stepNumber}
                                    </span>
                                    <span
                                        className={
                                            isCurrent
                                                ? 'font-medium text-foreground'
                                                : 'text-muted-foreground'
                                        }
                                    >
                                        {label}
                                    </span>
                                </li>
                            );
                        })}
                    </ol>

                    <section className={step === 1 ? 'grid gap-5' : 'hidden'}>
                        <div className="grid min-w-0 gap-4 sm:grid-cols-2">
                            <SelectField
                                label="Client"
                                name="client_id"
                                error={errors.client_id}
                                value={clientId}
                                onChange={(event) =>
                                    setClientId(event.target.value)
                                }
                                required
                            >
                                <option value="">Select client</option>
                                {clients.map((client) => (
                                    <option key={client.id} value={client.id}>
                                        {client.name} · {client.client_number}
                                    </option>
                                ))}
                            </SelectField>
                            <SelectField
                                label="Responsible attorney"
                                name="responsible_user_id"
                                error={errors.responsible_user_id}
                                value={responsibleUserId}
                                onChange={(event) =>
                                    setResponsibleUserId(event.target.value)
                                }
                                required
                            >
                                <option value="">
                                    Select responsible attorney
                                </option>
                                {responsibleUsers.map((user) => (
                                    <option key={user.id} value={user.id}>
                                        {user.name} · {user.email}
                                    </option>
                                ))}
                            </SelectField>
                            <SelectField
                                label="Matter type"
                                name="matter_type_id"
                                error={errors.matter_type_id}
                                value={matterTypeId}
                                onChange={(event) =>
                                    applyMatterType(event.target.value)
                                }
                                required
                            >
                                <option value="">Select matter type</option>
                                {matterTypes.map((matterType) => (
                                    <option
                                        key={matterType.id}
                                        value={matterType.id}
                                    >
                                        {matterType.name}
                                    </option>
                                ))}
                            </SelectField>
                            <TextField
                                label="Opened on"
                                name="opened_at"
                                type="date"
                                error={errors.opened_at}
                                defaultValue={today}
                                required
                            />
                            <TextField
                                label="Matter deadline"
                                name="deadline_at"
                                type="date"
                                error={errors.deadline_at}
                            />
                        </div>
                        <TextField
                            label="Matter title"
                            name="title"
                            error={errors.title}
                            value={title}
                            onChange={(event) => setTitle(event.target.value)}
                            placeholder="Chisomo Holdings contract dispute"
                            required
                        />
                        <TextAreaField
                            label="Matter description"
                            name="description"
                            error={errors.description}
                            placeholder="Summarize the instructions, dispute, or legal work to be completed."
                        />
                        <CustomFieldInputs
                            fields={customFields.filter(
                                (field) => field.step === 1,
                            )}
                            errors={errors}
                        />
                    </section>

                    <section className={step === 2 ? 'grid gap-5' : 'hidden'}>
                        <div className="grid min-w-0 gap-4 sm:grid-cols-2">
                            <SelectField
                                label="Billing type"
                                name="billing_type"
                                error={errors.billing_type}
                                value={billingType}
                                onChange={(event) =>
                                    setBillingType(
                                        event.target.value as BillingType,
                                    )
                                }
                            >
                                <option value="hourly">Hourly</option>
                                <option value="fixed_fee">Fixed fee</option>
                                <option value="contingency">Contingency</option>
                            </SelectField>
                            <TextField
                                label="Hourly rate (MWK)"
                                name="default_hourly_rate"
                                type="number"
                                error={errors.default_hourly_rate}
                                value={hourlyRate}
                                onChange={(event) =>
                                    setHourlyRate(event.target.value)
                                }
                                placeholder="75000"
                                min="0"
                                step="0.01"
                                required={billingType === 'hourly'}
                            />
                            <TextField
                                label="Matter budget (MWK)"
                                name="budget_amount"
                                type="number"
                                error={errors.budget_amount}
                                placeholder="2500000"
                                min="0"
                                step="0.01"
                            />
                            <SelectField
                                label="Matter status"
                                name="status"
                                error={errors.status}
                                defaultValue="open"
                            >
                                <option value="open">Open</option>
                                <option value="pending">Pending</option>
                                <option value="on_hold">On hold</option>
                            </SelectField>
                        </div>
                        <SelectField
                            label="Priority"
                            name="priority"
                            error={errors.priority}
                            defaultValue="normal"
                        >
                            <option value="low">Low</option>
                            <option value="normal">Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </SelectField>
                        <CustomFieldInputs
                            fields={customFields.filter(
                                (field) => field.step === 2,
                            )}
                            errors={errors}
                        />
                    </section>

                    <section className={step === 3 ? 'grid gap-4' : 'hidden'}>
                        <div className="grid gap-1">
                            <h3 className="font-medium">Attorney team</h3>
                            <p className="text-sm text-muted-foreground">
                                Select internal attorney participants. The
                                responsible attorney is included automatically.
                            </p>
                        </div>
                        {responsibleUsers.length === 0 ? (
                            <div className="rounded-lg border border-dashed px-4 py-8 text-center text-sm text-muted-foreground">
                                No active attorneys are available. Create an
                                active attorney position and assign it to a user
                                before opening a matter.
                            </div>
                        ) : (
                            <div className="grid gap-2 rounded-lg border p-3 sm:grid-cols-2">
                                {responsibleUsers.map((attorney) => (
                                    <label
                                        key={attorney.id}
                                        className="flex cursor-pointer items-start gap-3 rounded-md px-2 py-2 transition-colors hover:bg-muted/50"
                                    >
                                        <input
                                            className="mt-0.5 size-4 accent-primary"
                                            type="checkbox"
                                            name="attorney_ids[]"
                                            value={attorney.id}
                                            checked={attorneyIds.includes(
                                                attorney.id,
                                            )}
                                            onChange={() =>
                                                toggleAttorney(attorney.id)
                                            }
                                        />
                                        <span className="grid gap-0.5 text-sm">
                                            <span className="font-medium">
                                                {attorney.name}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {attorney.email}
                                            </span>
                                        </span>
                                    </label>
                                ))}
                            </div>
                        )}
                        <InputError message={errors.attorney_ids} />
                        <div className="mt-2 flex flex-col gap-3 border-t pt-5 sm:flex-row sm:items-start sm:justify-between">
                            <div className="grid gap-1">
                                <h3 className="font-medium">
                                    External parties
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    Add opposing parties, witnesses, and other
                                    contacts if applicable.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={addParticipant}
                            >
                                <Plus />
                                Add external party
                            </Button>
                        </div>
                        {participants.length === 0 ? (
                            <div className="rounded-lg border border-dashed px-4 py-10 text-center text-sm text-muted-foreground">
                                <UsersRound className="mx-auto mb-3 size-5" />
                                No external parties have been added.
                            </div>
                        ) : (
                            participants.map((participant, index) => (
                                <ParticipantFields
                                    key={participant.id}
                                    index={index}
                                    participant={participant}
                                    errors={errors}
                                    onRemove={() =>
                                        setParticipants((current) =>
                                            current.filter(
                                                (record) =>
                                                    record.id !==
                                                    participant.id,
                                            ),
                                        )
                                    }
                                />
                            ))
                        )}
                        <CustomFieldInputs
                            fields={customFields.filter(
                                (field) => field.step === 3,
                            )}
                            errors={errors}
                        />
                    </section>

                    <footer className="flex items-center justify-between gap-3 border-t pt-5">
                        {step > 1 ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    setStep((current) => current - 1)
                                }
                            >
                                <ChevronLeft />
                                Previous
                            </Button>
                        ) : (
                            <span />
                        )}
                        {step < 3 ? (
                            <Button
                                type="button"
                                formNoValidate
                                disabled={!canContinue}
                                onClick={(event) => {
                                    event.preventDefault();
                                    setStep((current) =>
                                        Math.min(steps.length, current + 1),
                                    );
                                }}
                            >
                                Continue
                                <ChevronRight />
                            </Button>
                        ) : (
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                Open matter
                            </Button>
                        )}
                    </footer>
                </>
            )}
        </Form>
    );
}

function ParticipantFields({
    index,
    participant,
    errors,
    onRemove,
}: {
    index: number;
    participant: Participant;
    errors: Record<string, string>;
    onRemove: () => void;
}) {
    const fieldName = (field: string): string =>
        `participants[${index}][${field}]`;
    const error = (field: string): string | undefined =>
        errors[`participants.${index}.${field}`];

    return (
        <section className="grid gap-4 rounded-lg border p-4">
            <div className="flex items-center justify-between gap-4">
                <h4 className="font-medium">Participant {index + 1}</h4>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onRemove}
                >
                    <Trash2 />
                    Remove
                </Button>
            </div>
            <div className="grid min-w-0 gap-4 sm:grid-cols-2">
                <SelectField
                    label="Role"
                    name={fieldName('role')}
                    error={error('role')}
                    defaultValue={participant.role}
                    required
                >
                    <option value="Opposing party">Opposing party</option>
                    <option value="Co-party">Co-party</option>
                    <option value="Witness">Witness</option>
                    <option value="Other">Other</option>
                </SelectField>
                <SelectField
                    label="Participant type"
                    name={fieldName('type')}
                    error={error('type')}
                    defaultValue={participant.type}
                    required
                >
                    <option value="individual">Individual</option>
                    <option value="organization">Organization</option>
                </SelectField>
                <TextField
                    label="Name"
                    name={fieldName('name')}
                    error={error('name')}
                    placeholder="Participant name"
                    required
                />
                <TextField
                    label="Contact person"
                    name={fieldName('contact_person')}
                    error={error('contact_person')}
                />
                <TextField
                    label="Email address"
                    name={fieldName('email')}
                    type="email"
                    error={error('email')}
                />
                <TextField
                    label="Phone number"
                    name={fieldName('phone')}
                    error={error('phone')}
                />
            </div>
        </section>
    );
}

function SelectField({
    label,
    name,
    error,
    children,
    required = false,
    value,
    defaultValue,
    onChange,
}: {
    label: string;
    name: string;
    error?: string;
    children: React.ReactNode;
    required?: boolean;
    value?: string;
    defaultValue?: string;
    onChange?: (event: React.ChangeEvent<HTMLSelectElement>) => void;
}) {
    const options = Children.toArray(children).flatMap((child) => {
        if (!isValidElement<{ value?: string; children?: unknown }>(child)) {
            return [];
        }

        const optionValue = child.props.value;
        const optionLabel = child.props.children;

        if (
            typeof optionValue !== 'string' ||
            typeof optionLabel !== 'string'
        ) {
            return [];
        }

        return [{ value: optionValue, label: optionLabel }];
    });

    return (
        <div className="grid min-w-0 gap-2">
            <Label htmlFor={`matter-${name}`}>{label}</Label>
            <SearchableSelect
                id={`matter-${name}`}
                name={name}
                required={required}
                value={value}
                defaultValue={defaultValue}
                options={options}
                searchable={options.length > 8}
                onChange={(nextValue) =>
                    onChange?.({
                        target: { value: nextValue },
                    } as React.ChangeEvent<HTMLSelectElement>)
                }
                error={error}
            />
        </div>
    );
}

function TextField({
    label,
    name,
    type = 'text',
    error,
    defaultValue,
    value,
    onChange,
    placeholder,
    required = false,
    min,
    step,
}: {
    label: string;
    name: string;
    type?: string;
    error?: string;
    defaultValue?: string;
    value?: string;
    onChange?: (event: React.ChangeEvent<HTMLInputElement>) => void;
    placeholder?: string;
    required?: boolean;
    min?: string;
    step?: string;
}) {
    return (
        <div className="grid min-w-0 gap-2">
            <Label htmlFor={`matter-${name}`}>{label}</Label>
            <Input
                id={`matter-${name}`}
                name={name}
                type={type}
                defaultValue={defaultValue}
                value={value}
                onChange={onChange}
                placeholder={placeholder}
                required={required}
                min={min}
                step={step}
            />
            <InputError message={error} />
        </div>
    );
}

function TextAreaField({
    label,
    name,
    error,
    placeholder,
}: {
    label: string;
    name: string;
    error?: string;
    placeholder?: string;
}) {
    return (
        <div className="grid min-w-0 gap-2">
            <Label htmlFor={`matter-${name}`}>{label}</Label>
            <textarea
                id={`matter-${name}`}
                name={name}
                placeholder={placeholder}
                className="min-h-28 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
            />
            <InputError message={error} />
        </div>
    );
}
