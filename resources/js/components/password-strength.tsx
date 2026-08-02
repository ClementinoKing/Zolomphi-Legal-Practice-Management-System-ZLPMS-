type PasswordStrengthProps = {
    password: string;
};

type Strength = {
    label: string;
    description: string;
    colorClass: string;
    filledBars: number;
};

function passwordStrength(password: string): Strength {
    const hasLowercase = /[a-z]/.test(password);
    const hasUppercase = /[A-Z]/.test(password);
    const hasNumber = /\d/.test(password);
    const hasSymbol = /[^A-Za-z0-9]/.test(password);
    const criteriaCount = [
        password.length >= 12,
        hasLowercase && hasUppercase,
        hasNumber,
        hasSymbol,
    ].filter(Boolean).length;

    if (password.length === 0) {
        return {
            label: 'Choose a password',
            description: 'Use 12 or more characters for a stronger password.',
            colorClass: 'bg-muted-foreground/30',
            filledBars: 0,
        };
    }

    if (criteriaCount <= 1) {
        return {
            label: 'Weak',
            description: 'Add uppercase letters, numbers, and symbols.',
            colorClass: 'bg-destructive',
            filledBars: 1,
        };
    }

    if (criteriaCount <= 3) {
        return {
            label: 'Fair',
            description: 'Add another character type or make it longer.',
            colorClass: 'bg-amber-500',
            filledBars: 2,
        };
    }

    return {
        label: 'Strong',
        description: 'This password meets the recommended strength checks.',
        colorClass: 'bg-emerald-600',
        filledBars: 3,
    };
}

export default function PasswordStrength({ password }: PasswordStrengthProps) {
    const strength = passwordStrength(password);

    return (
        <div className="grid gap-2" aria-live="polite">
            <div className="flex gap-1" aria-hidden="true">
                {[0, 1, 2].map((bar) => (
                    <span
                        key={bar}
                        className={`h-1 flex-1 rounded-full ${bar < strength.filledBars ? strength.colorClass : 'bg-muted'}`}
                    />
                ))}
            </div>
            <p className="text-xs text-muted-foreground">
                <span className="font-medium text-foreground">
                    {strength.label}
                </span>{' '}
                — {strength.description}
            </p>
        </div>
    );
}
