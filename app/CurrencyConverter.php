<?php

namespace App;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class CurrencyConverter
{
    public const BaseCurrency = 'MWK';

    /** @var array<string, string> */
    public const SupportedCurrencies = [
        'MWK' => 'Malawi kwacha (MWK)',
        'ZAR' => 'South African rand (ZAR)',
        'USD' => 'US dollar (USD)',
        'GBP' => 'Pound sterling (GBP)',
        'EUR' => 'Euro (EUR)',
    ];

    /**
     * Convert a monetary value using rates expressed against the Malawi kwacha.
     */
    public function convert(float|int|string $amount, string $fromCurrency, string $toCurrency): float
    {
        $fromCurrency = strtoupper($fromCurrency);
        $toCurrency = strtoupper($toCurrency);

        if ($fromCurrency === $toCurrency) {
            return round((float) $amount, 2);
        }

        $rates = $this->rates();
        $fromRate = $rates[$fromCurrency] ?? null;
        $toRate = $rates[$toCurrency] ?? null;

        if ($fromRate === null || $toRate === null || $fromRate <= 0) {
            throw new \RuntimeException('An exchange rate is not available for the requested currency conversion.');
        }

        return round(((float) $amount / $fromRate) * $toRate, 2);
    }

    public function canConvert(string $fromCurrency, string $toCurrency): bool
    {
        $fromCurrency = strtoupper($fromCurrency);
        $toCurrency = strtoupper($toCurrency);

        if ($fromCurrency === $toCurrency) {
            return true;
        }

        $rates = $this->rates();

        return isset($rates[$fromCurrency], $rates[$toCurrency]) && $rates[$fromCurrency] > 0;
    }

    /**
     * @return array<string, float>
     */
    public function rates(): array
    {
        $key = config('services.exchange_rate.key');

        if (! is_string($key) || $key === '') {
            return [self::BaseCurrency => 1.0];
        }

        $cacheKey = 'currency-converter:'.self::BaseCurrency;

        try {
            /** @var array<string, float> */
            return Cache::remember(
                $cacheKey,
                now()->addSeconds(max(60, (int) config('services.exchange_rate.cache_ttl', 86400))),
                fn (): array => $this->fetchRates($key),
            );
        } catch (Throwable) {
            $cachedRates = Cache::get($cacheKey);

            if (is_array($cachedRates)) {
                return $this->supportedRates($cachedRates);
            }

            return [self::BaseCurrency => 1.0];
        }
    }

    /**
     * @return array<string, float>
     */
    private function fetchRates(string $key): array
    {
        $baseUrl = config('services.exchange_rate.base_url');

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new ConnectionException('The ExchangeRate-API base URL is not configured.');
        }

        $response = Http::retry([100, 300])
            ->connectTimeout(3)
            ->timeout(5)
            ->get(rtrim($baseUrl, '/').'/'.$key.'/latest/'.self::BaseCurrency)
            ->throw();

        if ($response->json('result') !== 'success') {
            throw new \RuntimeException('The exchange-rate provider did not return a successful response.');
        }

        $rates = $response->json('conversion_rates');

        if (! is_array($rates)) {
            throw new \RuntimeException('The exchange-rate provider did not return conversion rates.');
        }

        return $this->supportedRates($rates);
    }

    /**
     * @param  array<array-key, mixed>  $rates
     * @return array<string, float>
     */
    private function supportedRates(array $rates): array
    {
        $supportedRates = [self::BaseCurrency => 1.0];

        foreach (array_keys(self::SupportedCurrencies) as $currency) {
            $rate = $rates[$currency] ?? null;

            if (is_numeric($rate) && (float) $rate > 0) {
                $supportedRates[$currency] = (float) $rate;
            }
        }

        return $supportedRates;
    }
}
