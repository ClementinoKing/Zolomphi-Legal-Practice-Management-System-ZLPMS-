<?php

namespace App\Services;

use App\Models\GeneralSetting;

class InvoiceSettings
{
    /**
     * @return array{
     *     tax_enabled: bool,
     *     tax_label: string,
     *     default_tax_rate: string,
     *     levy_enabled: bool,
     *     levy_label: string,
     *     levy_rate: string,
     *     number_prefix: string,
     *     number_padding: int,
     *     next_number: int,
     *     default_terms: string,
     *     payment: array{instructions: string, bank_name: string, account_name: string, account_number: string, account_branch: string, swift_code: string},
     *     template: array{layout: string, accent_color: string, header_note: string, footer_note: string, show_tax_id: bool, show_matter_reference: bool, logo: array{id: string, name: string, source: string, preview_url: string}|null}
     * }
     */
    public function values(?GeneralSetting $settings = null): array
    {
        $settings ??= GeneralSetting::query()->first();

        return array_replace_recursive($this->defaults(), $settings?->invoice_settings ?? []);
    }

    /**
     * @param  array{
     *     tax_enabled: bool,
     *     tax_label: string,
     *     default_tax_rate: string,
     *     levy_enabled: bool,
     *     levy_label: string,
     *     levy_rate: string,
     *     number_prefix: string,
     *     number_padding: int,
     *     next_number: int,
     *     default_terms: string,
     *     payment: array{instructions: string, bank_name: string, account_name: string, account_number: string, account_branch: string, swift_code: string},
     *     template: array{layout: string, accent_color: string, header_note: string, footer_note: string, show_tax_id: bool, show_matter_reference: bool, logo: array{id: string, name: string, source: string, preview_url: string}|null}
     * }  $settings
     */
    public function update(GeneralSetting $generalSettings, array $settings): void
    {
        $generalSettings->forceFill([
            'invoice_settings' => array_replace_recursive($this->values($generalSettings), $settings),
        ])->save();
    }

    /**
     * @return array{
     *     tax_enabled: bool,
     *     tax_label: string,
     *     default_tax_rate: string,
     *     levy_enabled: bool,
     *     levy_label: string,
     *     levy_rate: string,
     *     number_prefix: string,
     *     number_padding: int,
     *     next_number: int,
     *     default_terms: string,
     *     payment: array{instructions: string, bank_name: string, account_name: string, account_number: string, account_branch: string, swift_code: string},
     *     template: array{layout: string, accent_color: string, header_note: string, footer_note: string, show_tax_id: bool, show_matter_reference: bool, logo: array{id: string, name: string, source: string, preview_url: string}|null}
     * }
     */
    private function defaults(): array
    {
        return [
            'tax_enabled' => true,
            'tax_label' => 'VAT',
            'default_tax_rate' => '16.50',
            'levy_enabled' => true,
            'levy_label' => 'MLS Levy',
            'levy_rate' => '1.00',
            'number_prefix' => 'INV',
            'number_padding' => 6,
            'next_number' => 1,
            'default_terms' => 'Payment is due within 30 days.',
            'payment' => [
                'instructions' => '',
                'bank_name' => '',
                'account_name' => '',
                'account_number' => '',
                'account_branch' => '',
                'swift_code' => '',
            ],
            'template' => [
                'layout' => 'classic',
                'accent_color' => '#191E5E',
                'header_note' => 'Thank you for your instructions.',
                'footer_note' => 'Please use the invoice number as your payment reference.',
                'show_tax_id' => true,
                'show_matter_reference' => true,
                'logo' => null,
            ],
        ];
    }
}
