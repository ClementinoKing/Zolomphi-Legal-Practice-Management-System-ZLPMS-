import { Form } from '@inertiajs/react';
import AdministrationController from '@/actions/App/Http/Controllers/AdministrationController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type FirmSettings = {
    firm_name: string | null;
    invoice_contact_name: string | null;
    email: string | null;
    phone: string | null;
    website: string | null;
    address_line1: string | null;
    address_line2: string | null;
    city: string | null;
    state: string | null;
    postal_code: string | null;
    country: string | null;
    tax_id: string | null;
    license_number: string | null;
    timezone: string;
    currency: string;
};

export default function FirmSettingsForm({
    settings,
    currencies,
}: {
    settings: FirmSettings;
    currencies: Record<string, string>;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Firm profile</CardTitle>
                <CardDescription>
                    The identity and contact details shown across firm
                    documents.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    {...AdministrationController.updateFirmSettings.form()}
                    className="grid gap-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-4 md:grid-cols-2">
                                <Field
                                    label="Firm name"
                                    name="firm_name"
                                    defaultValue={settings.firm_name}
                                    error={errors.firm_name}
                                    required
                                />
                                <Field
                                    label="Invoice contact person"
                                    name="invoice_contact_name"
                                    defaultValue={settings.invoice_contact_name}
                                    error={errors.invoice_contact_name}
                                />
                                <Field
                                    label="Email address"
                                    name="email"
                                    type="email"
                                    defaultValue={settings.email}
                                    error={errors.email}
                                />
                                <Field
                                    label="Phone"
                                    name="phone"
                                    defaultValue={settings.phone}
                                    error={errors.phone}
                                />
                                <Field
                                    label="Website"
                                    name="website"
                                    type="url"
                                    defaultValue={settings.website}
                                    error={errors.website}
                                />
                                <Field
                                    label="Tax ID"
                                    name="tax_id"
                                    defaultValue={settings.tax_id}
                                    error={errors.tax_id}
                                />
                                <Field
                                    label="Licence number"
                                    name="license_number"
                                    defaultValue={settings.license_number}
                                    error={errors.license_number}
                                />
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <Field
                                    label="Address line 1"
                                    name="address_line1"
                                    defaultValue={settings.address_line1}
                                    error={errors.address_line1}
                                />
                                <Field
                                    label="Address line 2"
                                    name="address_line2"
                                    defaultValue={settings.address_line2}
                                    error={errors.address_line2}
                                />
                                <Field
                                    label="City"
                                    name="city"
                                    defaultValue={settings.city}
                                    error={errors.city}
                                />
                                <Field
                                    label="State or district"
                                    name="state"
                                    defaultValue={settings.state}
                                    error={errors.state}
                                />
                                <Field
                                    label="Postal code"
                                    name="postal_code"
                                    defaultValue={settings.postal_code}
                                    error={errors.postal_code}
                                />
                                <Field
                                    label="Country"
                                    name="country"
                                    defaultValue={settings.country}
                                    error={errors.country}
                                />
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <Field
                                    label="Timezone"
                                    name="timezone"
                                    defaultValue={settings.timezone}
                                    error={errors.timezone}
                                    required
                                />
                                <div className="grid gap-2">
                                    <Label htmlFor="currency">
                                        Display currency
                                    </Label>
                                    <select
                                        id="currency"
                                        name="currency"
                                        defaultValue={settings.currency}
                                        className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        required
                                    >
                                        {Object.entries(currencies).map(
                                            ([code, label]) => (
                                                <option key={code} value={code}>
                                                    {label}
                                                </option>
                                            ),
                                        )}
                                    </select>
                                    <InputError message={errors.currency} />
                                </div>
                            </div>

                            <div>
                                <Button disabled={processing}>
                                    {processing && <Spinner />}
                                    Save firm settings
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}

function Field({
    label,
    name,
    defaultValue,
    error,
    required = false,
    type = 'text',
    maxLength,
}: {
    label: string;
    name: string;
    defaultValue: string | null;
    error?: string;
    required?: boolean;
    type?: string;
    maxLength?: number;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                type={type}
                defaultValue={defaultValue ?? ''}
                required={required}
                maxLength={maxLength}
            />
            <InputError message={error} />
        </div>
    );
}
