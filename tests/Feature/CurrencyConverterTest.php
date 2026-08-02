<?php

use App\CurrencyConverter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('it fetches and caches the supported rates using the Malawi kwacha as the base currency', function () {
    config()->set('services.exchange_rate.key', 'test-key');
    config()->set('services.exchange_rate.base_url', 'https://rates.example.test/v6');
    config()->set('services.exchange_rate.cache_ttl', 3600);
    Cache::flush();
    Http::preventStrayRequests();
    Http::fake([
        'https://rates.example.test/v6/test-key/latest/MWK' => Http::response([
            'result' => 'success',
            'conversion_rates' => [
                'MWK' => 1,
                'ZAR' => 0.011,
                'USD' => 0.00058,
                'GBP' => 0.00046,
                'EUR' => 0.00054,
            ],
        ]),
    ]);

    $converter = app(CurrencyConverter::class);

    expect($converter->rates())->toMatchArray([
        'MWK' => 1.0,
        'ZAR' => 0.011,
        'USD' => 0.00058,
        'GBP' => 0.00046,
        'EUR' => 0.00054,
    ]);
    expect($converter->convert(1000, 'MWK', 'USD'))->toBe(0.58);
    expect($converter->convert(10, 'USD', 'MWK'))->toBe(17241.38);

    $converter->rates();

    Http::assertSentCount(1);
});

test('it keeps the base currency available when exchange rates are not configured', function () {
    config()->set('services.exchange_rate.key', null);

    $converter = app(CurrencyConverter::class);

    expect($converter->rates())->toBe(['MWK' => 1.0]);
    expect($converter->canConvert('MWK', 'MWK'))->toBeTrue();
    expect($converter->canConvert('MWK', 'USD'))->toBeFalse();
});
