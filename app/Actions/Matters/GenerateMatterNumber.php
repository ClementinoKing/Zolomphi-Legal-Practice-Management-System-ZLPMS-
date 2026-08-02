<?php

namespace App\Actions\Matters;

use App\Models\GeneralSetting;
use App\Models\Matter;
use App\Services\PracticeRecordSettings;

class GenerateMatterNumber
{
    public function __construct(private readonly PracticeRecordSettings $settings) {}

    public function handle(): string
    {
        $generalSettings = GeneralSetting::query()->firstOrCreate();
        $generalSettings = GeneralSetting::query()->lockForUpdate()->findOrFail($generalSettings->id);
        $values = $this->settings->matterValues($generalSettings);
        $nextNumber = $values['next_number'];
        $matterNumber = $this->format($values['number_prefix'], $nextNumber, $values['number_padding']);

        while (Matter::query()->withTrashed()->where('matter_number', $matterNumber)->exists()) {
            $nextNumber++;
            $matterNumber = $this->format($values['number_prefix'], $nextNumber, $values['number_padding']);
        }

        $this->settings->updateMatter($generalSettings, [
            ...$values,
            'next_number' => $nextNumber + 1,
        ]);

        return $matterNumber;
    }

    private function format(string $prefix, int $number, int $padding): string
    {
        return $prefix.'-'.str_pad((string) $number, $padding, '0', STR_PAD_LEFT);
    }
}
