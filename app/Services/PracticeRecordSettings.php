<?php

namespace App\Services;

use App\Models\GeneralSetting;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class PracticeRecordSettings
{
    /** @var list<string> */
    public const FieldTypes = ['text', 'textarea', 'number', 'date', 'select', 'boolean'];

    /**
     * @return array{number_prefix: string, number_padding: int, next_number: int, fields: list<array<string, mixed>>}
     */
    public function clientValues(?GeneralSetting $settings = null): array
    {
        $settings ??= GeneralSetting::query()->first();

        return $this->values($settings?->client_settings, 'CLI');
    }

    /**
     * @return array{number_prefix: string, number_padding: int, next_number: int, fields: list<array<string, mixed>>}
     */
    public function matterValues(?GeneralSetting $settings = null): array
    {
        $settings ??= GeneralSetting::query()->first();

        return $this->values($settings?->matter_settings, 'MAT');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateClient(GeneralSetting $settings, array $attributes): void
    {
        $current = $this->clientValues($settings);
        $settings->forceFill([
            'client_settings' => [
                'number_prefix' => $attributes['number_prefix'],
                'number_padding' => (int) $attributes['number_padding'],
                'next_number' => (int) $attributes['next_number'],
                'fields' => $this->normaliseFields($attributes['fields'] ?? [], $current['fields'], true),
            ],
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateMatter(GeneralSetting $settings, array $attributes): void
    {
        $current = $this->matterValues($settings);
        $settings->forceFill([
            'matter_settings' => [
                'number_prefix' => $attributes['number_prefix'],
                'number_padding' => (int) $attributes['number_padding'],
                'next_number' => (int) $attributes['next_number'],
                'fields' => $this->normaliseFields($attributes['fields'] ?? [], $current['fields'], false),
            ],
        ])->save();
    }

    /**
     * @param  list<array<string, mixed>>  $definitions
     */
    public function validateValues(Validator $validator, array $definitions, mixed $submittedValues, ?string $clientType = null): void
    {
        if ($submittedValues === null) {
            $submittedValues = [];
        }

        if (! is_array($submittedValues)) {
            $validator->errors()->add('custom_fields', 'Custom field values must be submitted as an object.');

            return;
        }

        $fields = $this->applicableFields($definitions, $clientType);
        $fieldKeys = array_column($fields, 'key');

        foreach (array_keys($submittedValues) as $key) {
            if (! is_string($key) || ! in_array($key, $fieldKeys, true)) {
                $validator->errors()->add('custom_fields', 'One or more submitted custom fields are not configured for this record.');

                return;
            }
        }

        foreach ($fields as $field) {
            $key = $field['key'];
            $value = $submittedValues[$key] ?? null;
            $errorKey = 'custom_fields.'.$key;

            if ($field['required'] && ($value === null || $value === '')) {
                $validator->errors()->add($errorKey, $field['label'].' is required.');

                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            if ($field['type'] === 'number' && ! is_numeric($value)) {
                $validator->errors()->add($errorKey, $field['label'].' must be a number.');
            }

            if ($field['type'] === 'date' && strtotime((string) $value) === false) {
                $validator->errors()->add($errorKey, $field['label'].' must be a valid date.');
            }

            if ($field['type'] === 'select' && ! in_array((string) $value, $field['options'], true)) {
                $validator->errors()->add($errorKey, 'Select a valid '.$field['label'].'.');
            }

            if ($field['type'] === 'boolean' && ! in_array($value, [true, false, 0, 1, '0', '1'], true)) {
                $validator->errors()->add($errorKey, $field['label'].' must be yes or no.');
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $definitions
     * @param  array<string, mixed>|null  $values
     * @return array<string, bool|float|string>
     */
    public function valuesForStorage(array $definitions, ?array $values, ?string $clientType = null): array
    {
        $values ??= [];
        $normalised = [];

        foreach ($this->applicableFields($definitions, $clientType) as $field) {
            $value = $values[$field['key']] ?? null;

            if ($value === null || $value === '') {
                $normalised[$field['key']] = '';

                continue;
            }

            $normalised[$field['key']] = match ($field['type']) {
                'boolean' => in_array($value, [true, 1, '1'], true),
                'number' => (float) $value,
                default => (string) $value,
            };
        }

        return $normalised;
    }

    /**
     * @param  list<array<string, mixed>>  $definitions
     * @return list<array{key: string, label: string, type: string, placeholder: string, options: list<string>, required: bool, applies_to: list<string>, step: int}>
     */
    public function applicableFields(array $definitions, ?string $clientType = null): array
    {
        return collect($definitions)
            ->filter(fn (array $field): bool => $clientType === null || in_array($clientType, $field['applies_to'] ?? [], true))
            ->map(fn (array $field): array => [
                'key' => (string) $field['key'],
                'label' => (string) $field['label'],
                'type' => (string) $field['type'],
                'placeholder' => (string) ($field['placeholder'] ?? ''),
                'options' => array_values($field['options'] ?? []),
                'required' => (bool) ($field['required'] ?? false),
                'applies_to' => array_values($field['applies_to'] ?? []),
                'step' => (int) ($field['step'] ?? 1),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array{number_prefix: string, number_padding: int, next_number: int, fields: list<array<string, mixed>>}
     */
    private function values(?array $stored, string $prefix): array
    {
        return array_replace([
            'number_prefix' => $prefix,
            'number_padding' => 6,
            'next_number' => 1,
            'fields' => [],
        ], $stored ?? []);
    }

    /**
     * @param  list<array<string, mixed>>  $submittedFields
     * @param  list<array<string, mixed>>  $existingFields
     * @return list<array{ id: string, key: string, label: string, type: string, placeholder: string, options: list<string>, required: bool, applies_to: list<string>, step: int, sort_order: int}>
     */
    private function normaliseFields(array $submittedFields, array $existingFields, bool $isClient): array
    {
        $existing = collect($existingFields)->keyBy('id');

        return array_values(array_map(function (array $field, int $index) use ($existing, $isClient): array {
            $existingField = $existing->get($field['id'] ?? '');
            $type = in_array($field['type'] ?? '', self::FieldTypes, true) ? $field['type'] : 'text';
            $options = $type === 'select'
                ? array_values(array_filter(array_map('trim', $field['options'] ?? [])))
                : [];

            return [
                'id' => is_array($existingField) ? $existingField['id'] : (string) Str::ulid(),
                'key' => is_array($existingField) ? $existingField['key'] : 'custom_'.Str::lower(Str::ulid()),
                'label' => trim((string) $field['label']),
                'type' => $type,
                'placeholder' => trim((string) ($field['placeholder'] ?? '')),
                'options' => $options,
                'required' => (bool) ($field['required'] ?? false),
                'applies_to' => $isClient
                    ? array_values(array_intersect($field['applies_to'] ?? [], ['individual', 'organization']))
                    : ['individual', 'organization'],
                'step' => (int) ($field['step'] ?? 1),
                'sort_order' => $index,
            ];
        }, $submittedFields, array_keys($submittedFields)));
    }
}
