import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import PasswordStrength from '@/components/password-strength';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/password/change';

type Props = {
    passwordRules: string;
};

export default function ForcePasswordChange({ passwordRules }: Props) {
    const [password, setPassword] = useState('');

    return (
        <>
            <Head title="Create a new password" />

            <Form
                action={update.url()}
                method="put"
                resetOnSuccess={['password', 'password_confirmation']}
                className="grid gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <p className="text-sm text-muted-foreground">
                            Your administrator gave you a temporary password.
                            Create a new password to continue.
                        </p>

                        <div className="grid gap-2">
                            <Label htmlFor="password">New password</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                value={password}
                                onChange={(event) =>
                                    setPassword(event.target.value)
                                }
                                autoComplete="new-password"
                                autoFocus
                                placeholder="Create a secure password"
                                passwordrules={passwordRules}
                            />
                            <PasswordStrength password={password} />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Confirm new password
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autoComplete="new-password"
                                placeholder="Confirm your new password"
                                passwordrules={passwordRules}
                            />
                            <InputError
                                message={errors.password_confirmation}
                            />
                        </div>

                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Save new password
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}

ForcePasswordChange.layout = {
    title: 'Create your new password',
    description:
        'A new password is required before you can access the workspace.',
};
