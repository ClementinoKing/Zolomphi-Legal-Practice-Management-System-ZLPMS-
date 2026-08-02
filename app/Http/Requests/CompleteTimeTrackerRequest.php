<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CompleteTimeTrackerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-administration') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'entry_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:5000'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'is_billable' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_billable')) {
            $this->merge(['is_billable' => $this->boolean('is_billable')]);
        }
    }
}
