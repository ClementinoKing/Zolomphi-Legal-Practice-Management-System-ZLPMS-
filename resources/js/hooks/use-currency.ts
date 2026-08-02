import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import type { CurrencySettings } from '@/lib/currency';
import { fallbackCurrencySettings, formatCurrency } from '@/lib/currency';

type PageProps = {
    currency?: CurrencySettings;
};

export function useCurrency() {
    const { currency = fallbackCurrencySettings } = usePage<PageProps>().props;

    return useMemo(
        () => ({
            settings: currency,
            formatMoney: (
                value: number | string,
                sourceCurrency = currency.base,
                maximumFractionDigits = 2,
            ): string =>
                formatCurrency(
                    value,
                    sourceCurrency,
                    currency,
                    maximumFractionDigits,
                ),
        }),
        [currency],
    );
}
