<?php

namespace App\Actions\Matters;

use App\Models\GeneralSetting;
use App\Models\Matter;
use App\Models\MatterType;
use App\Services\PracticeRecordSettings;
use Illuminate\Support\Str;

class GenerateMatterNumber
{
    public function __construct(private readonly PracticeRecordSettings $settings) {}

    public function handle(MatterType $matterType): string
    {
        $generalSettings = GeneralSetting::query()->firstOrCreate();
        $generalSettings = GeneralSetting::query()->lockForUpdate()->findOrFail($generalSettings->id);
        $values = $this->settings->matterValues($generalSettings);
        $nextNumber = $values['next_number'];
        $matterNumber = $this->format($values['number_prefix'], $matterType, $nextNumber, $values['number_padding']);

        while (Matter::query()->withTrashed()->where('matter_number', $matterNumber)->exists()) {
            $nextNumber++;
            $matterNumber = $this->format($values['number_prefix'], $matterType, $nextNumber, $values['number_padding']);
        }

        $this->settings->updateMatter($generalSettings, [
            ...$values,
            'next_number' => $nextNumber + 1,
        ]);

        return $matterNumber;
    }

    private function format(string $prefix, MatterType $matterType, int $number, int $padding): string
    {
        return $prefix.'-'.$this->shortCode($matterType).'-'.str_pad((string) $number, $padding, '0', STR_PAD_LEFT);
    }

    private function shortCode(MatterType $matterType): string
    {
        if ($matterType->short_code !== null && $matterType->short_code !== '') {
            return $matterType->short_code;
        }

        $shortCode = collect(preg_split('/[^A-Za-z0-9]+/', $matterType->code) ?: [])
            ->filter()
            ->map(fn (string $segment): string => Str::upper(Str::substr($segment, 0, 1)))
            ->implode('');

        return $shortCode !== '' ? $shortCode : 'MAT';
    }
}
