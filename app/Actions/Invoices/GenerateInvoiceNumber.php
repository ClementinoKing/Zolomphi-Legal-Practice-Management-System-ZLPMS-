<?php

namespace App\Actions\Invoices;

use App\Models\GeneralSetting;
use App\Models\Invoice;
use App\Services\InvoiceSettings;

class GenerateInvoiceNumber
{
    public function __construct(private readonly InvoiceSettings $settings) {}

    public function handle(): string
    {
        $generalSettings = GeneralSetting::query()->firstOrCreate();
        $generalSettings = GeneralSetting::query()->lockForUpdate()->findOrFail($generalSettings->id);
        $values = $this->settings->values($generalSettings);
        $nextNumber = $values['next_number'];
        $invoiceNumber = $this->format($values['number_prefix'], $nextNumber, $values['number_padding']);

        while (Invoice::query()->where('invoice_number', $invoiceNumber)->exists()) {
            $nextNumber++;
            $invoiceNumber = $this->format($values['number_prefix'], $nextNumber, $values['number_padding']);
        }

        $this->settings->update($generalSettings, [
            ...$values,
            'next_number' => $nextNumber + 1,
        ]);

        return $invoiceNumber;
    }

    private function format(string $prefix, int $number, int $padding): string
    {
        return $prefix.'-'.str_pad((string) $number, $padding, '0', STR_PAD_LEFT);
    }
}
