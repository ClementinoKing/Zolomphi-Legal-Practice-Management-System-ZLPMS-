<?php

namespace App\Http\Requests;

use App\Models\GeneralSetting;
use App\Services\PracticeRecordSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ClientRequest extends FormRequest
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
            'type' => ['required', Rule::in(['individual', 'organization'])],
            'name' => ['required', 'string', 'max:255'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'organization_name' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'alternate_phone' => ['nullable', 'string', 'max:50'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'custom_fields' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $type = $this->string('type')->toString();
        $name = $this->string('name')->toString();

        $this->merge([
            'full_name' => $type === 'individual' ? $name : null,
            'organization_name' => $type === 'organization' ? $name : null,
        ]);
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            app(PracticeRecordSettings::class)->validateValues(
                $validator,
                app(PracticeRecordSettings::class)->clientValues(GeneralSetting::query()->first())['fields'],
                $this->input('custom_fields'),
                $this->string('type')->toString(),
            );
        }];
    }

    /**
     * @return array<string, mixed>
     */
    public function customFields(): array
    {
        $settings = app(PracticeRecordSettings::class);
        $values = $this->input('custom_fields', []);

        return is_array($values)
            ? $settings->valuesForStorage(
                $settings->clientValues(GeneralSetting::query()->first())['fields'],
                $values,
                $this->string('type')->toString(),
            )
            : [];
    }
}
