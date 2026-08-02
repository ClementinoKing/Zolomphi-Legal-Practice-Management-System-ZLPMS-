<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UpdateInvoiceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('invoices.configure') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'tax_enabled' => ['required', 'boolean'],
            'tax_label' => ['required', 'string', 'max:30'],
            'default_tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'number_prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/'],
            'number_padding' => ['required', 'integer', 'min:3', 'max:10'],
            'next_number' => ['required', 'integer', 'min:1', 'max:999999999'],
            'default_terms' => ['nullable', 'string', 'max:5000'],
            'template' => ['required', 'array'],
            'template.layout' => ['required', 'in:classic,modern,minimal'],
            'template.accent_color' => ['required', 'regex:/^#[A-Fa-f0-9]{6}$/'],
            'template.header_note' => ['nullable', 'string', 'max:500'],
            'template.footer_note' => ['nullable', 'string', 'max:500'],
            'template.show_tax_id' => ['required', 'boolean'],
            'template.show_matter_reference' => ['required', 'boolean'],
            'template.logo' => ['nullable', 'array'],
            'template.logo.id' => ['required_with:template.logo', 'string', 'max:255'],
            'logo_file' => ['nullable', File::image()->max('5mb')],
        ];
    }
}
