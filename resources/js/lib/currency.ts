export type CurrencySettings = {
    base: string;
    code: string;
    rates: Record<string, number>;
};

export const fallbackCurrencySettings: CurrencySettings = {
    base: 'MWK',
    code: 'MWK',
    rates: { MWK: 1 },
};

export function formatCurrency(
    value: number | string,
    sourceCurrency: string,
    settings: CurrencySettings,
    maximumFractionDigits = 2,
): string {
    const source = sourceCurrency.toUpperCase();
    const target = settings.code.toUpperCase();
    const sourceRate = settings.rates[source];
    const targetRate = settings.rates[target];
    const canConvert =
        source === target ||
        (typeof sourceRate === 'number' &&
            sourceRate > 0 &&
            typeof targetRate === 'number' &&
            targetRate > 0);
    const amount = Number(value) || 0;
    const converted =
        canConvert && source !== target
            ? (amount / (sourceRate ?? 1)) * (targetRate ?? 1)
            : amount;

    return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: canConvert ? target : source,
        maximumFractionDigits,
    }).format(converted);
}
