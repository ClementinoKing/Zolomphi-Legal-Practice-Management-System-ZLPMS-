<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Models\GeneralSetting;
use App\Services\ClientDuplicateMatcher;
use App\Services\PracticeRecordSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $client = $this->route('client');

        return $this->isMethod('POST')
            ? ($user?->can('create', Client::class) ?? false)
            : ($client instanceof Client && ($user?->can('update', $client) ?? false));
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
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^\+?[0-9\s().-]+$/'],
            'alternate_phone' => ['nullable', 'string', 'max:50', 'regex:/^\+?[0-9\s().-]+$/'],
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
            'duplicate_decisions' => [$this->isMethod('POST') ? 'sometimes' : 'prohibited', 'array', 'max:25'],
            'duplicate_decisions.*.candidate_id' => ['required', 'integer', 'distinct', 'exists:clients,id'],
            'duplicate_decisions.*.decision' => ['required', Rule::in(['duplicate', 'not_duplicate'])],
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

            if (! $this->isMethod('POST') || $validator->errors()->isNotEmpty()) {
                return;
            }

            $candidates = app(ClientDuplicateMatcher::class)->findCandidates($this->all());
            $expectedIds = collect($candidates)->pluck('id')->sort()->values()->all();
            $submittedIds = collect($this->input('duplicate_decisions', []))
                ->pluck('candidate_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->sort()
                ->values()
                ->all();

            if ($expectedIds !== $submittedIds) {
                $validator->errors()->add(
                    'duplicate_decisions',
                    'Review each possible matching client before saving. Please check for matches again.',
                );
            }
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
