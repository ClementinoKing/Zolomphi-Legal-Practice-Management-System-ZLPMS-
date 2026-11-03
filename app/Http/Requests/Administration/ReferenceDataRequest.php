<?php

namespace App\Http\Requests\Administration;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ReferenceDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match ($this->route('resource')) {
            'matter-types' => $this->user()?->hasPermission('matter-types.manage') ?? false,
            'billing-items' => $this->user()?->hasPermission('billing-items.manage') ?? false,
            'expense-categories' => $this->user()?->hasPermission('expense-categories.manage') ?? false,
            'payment-methods' => $this->user()?->hasPermission('payment-methods.manage') ?? false,
            default => false,
        };
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $resource = $this->string('resource', $this->route('resource'))->toString();
        $record = $this->route('record');

        $rules = [
            'name' => ['required', 'string', 'max:255', Rule::unique($this->tableFor($resource), 'name')->ignore($record)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];

        return match ($resource) {
            'matter-types' => [
                ...$rules,
                'short_code' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9]{2,10}$/', Rule::unique('matter_types', 'short_code')->ignore($record)],
                'default_billing_type' => ['required', Rule::in(['hourly', 'fixed_fee', 'contingency'])],
                'default_hourly_rate' => ['nullable', 'numeric', 'min:0'],
            ],
            'billing-items' => [
                ...$rules,
                'unit' => ['required', 'string', 'max:50'],
                'default_amount' => ['required', 'numeric', 'min:0'],
                'tax_rate' => ['required', 'numeric', 'between:0,100'],
            ],
            'expense-categories' => [
                ...$rules,
                'is_billable_by_default' => ['required', 'boolean'],
            ],
            default => $rules,
        };
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('resource') !== 'matter-types' || ! $this->has('short_code')) {
            return;
        }

        $this->merge([
            'short_code' => Str::upper($this->string('short_code')->trim()->toString()),
        ]);
    }

    private function tableFor(string $resource): string
    {
        return match ($resource) {
            'matter-types' => 'matter_types',
            'billing-items' => 'billing_items',
            'expense-categories' => 'expense_categories',
            'payment-methods' => 'payment_methods',
            default => throw new \InvalidArgumentException('Unsupported reference-data resource.'),
        };
    }
}
