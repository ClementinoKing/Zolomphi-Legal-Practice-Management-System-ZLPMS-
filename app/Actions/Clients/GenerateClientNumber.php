<?php

namespace App\Actions\Clients;

use App\Models\Client;
use App\Models\GeneralSetting;
use App\Services\PracticeRecordSettings;

class GenerateClientNumber
{
    public function __construct(private readonly PracticeRecordSettings $settings) {}

    public function handle(): string
    {
        $generalSettings = GeneralSetting::query()->firstOrCreate();
        $generalSettings = GeneralSetting::query()->lockForUpdate()->findOrFail($generalSettings->id);
        $values = $this->settings->clientValues($generalSettings);
        $nextNumber = $values['next_number'];
        $clientNumber = $this->format($values['number_prefix'], $nextNumber, $values['number_padding']);

        while (Client::query()->withTrashed()->where('client_number', $clientNumber)->exists()) {
            $nextNumber++;
            $clientNumber = $this->format($values['number_prefix'], $nextNumber, $values['number_padding']);
        }

        $this->settings->updateClient($generalSettings, [
            ...$values,
            'next_number' => $nextNumber + 1,
        ]);

        return $clientNumber;
    }

    private function format(string $prefix, int $number, int $padding): string
    {
        return $prefix.'-'.str_pad((string) $number, $padding, '0', STR_PAD_LEFT);
    }
}
