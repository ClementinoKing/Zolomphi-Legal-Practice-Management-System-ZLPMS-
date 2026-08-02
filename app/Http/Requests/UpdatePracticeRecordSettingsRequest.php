<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

abstract class UpdatePracticeRecordSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-administration') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'number_prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/'],
            'number_padding' => ['required', 'integer', 'min:3', 'max:10'],
            'next_number' => ['required', 'integer', 'min:1', 'max:999999999'],
            'fields' => ['nullable', 'array', 'max:50'],
            'fields.*.id' => ['nullable', 'string', 'max:64'],
            'fields.*.label' => ['required', 'string', 'max:100'],
            'fields.*.type' => ['required', 'in:text,textarea,number,date,select,boolean'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'fields.*.options' => ['nullable', 'array', 'max:50'],
            'fields.*.options.*' => ['required_with:fields.*.options', 'string', 'max:100'],
            'fields.*.required' => ['required', 'boolean'],
        ];
    }
}
