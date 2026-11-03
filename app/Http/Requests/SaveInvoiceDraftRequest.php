<?php

namespace App\Http\Requests;

use App\CurrencyConverter;
use App\Models\Invoice;
use App\Models\Matter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveInvoiceDraftRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $this->user()?->can(
            $invoice instanceof Invoice ? 'update' : 'create',
            $invoice instanceof Invoice ? $invoice : Invoice::class,
        ) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')],
            'matter_id' => ['nullable', 'integer', Rule::exists('matters', 'id')],
            'currency' => ['required', 'string', Rule::in(array_keys(CurrencyConverter::SupportedCurrencies))],
            'issued_at' => ['required', 'date'],
            'due_at' => ['required', 'date', 'after_or_equal:issued_at'],
            'our_reference' => ['nullable', 'string', 'max:255'],
            'your_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'terms' => ['nullable', 'string', 'max:10000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.source_type' => ['required', Rule::in(['time_entry', 'expense', 'matter_charge', 'manual'])],
            'items.*.source_id' => ['nullable', 'integer'],
            'items.*.description' => ['required', 'string', 'max:1000'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.01', 'max:100000'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $clientId = $this->integer('client_id');
                $matterId = $this->input('matter_id');

                if ($matterId !== null && ! Matter::query()->whereKey($matterId)->where('client_id', $clientId)->exists()) {
                    $validator->errors()->add('matter_id', 'Select a matter that belongs to the invoice client.');
                }

                foreach ($this->input('items', []) as $index => $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    if (($item['source_type'] ?? null) !== 'manual' && empty($item['source_id'])) {
                        $validator->errors()->add("items.$index.source_id", 'Select the billing record to invoice.');
                    }

                    if (($item['source_type'] ?? null) === 'manual') {
                        foreach (['quantity', 'unit_price', 'tax_rate'] as $field) {
                            if (! array_key_exists($field, $item) || $item[$field] === null || $item[$field] === '') {
                                $validator->errors()->add("items.$index.$field", 'This value is required for a manual line.');
                            }
                        }
                    }
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('currency')) {
            $this->merge(['currency' => strtoupper($this->string('currency')->trim()->toString())]);
        }
    }
}
