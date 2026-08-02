<?php

namespace App\Http\Requests;

class UpdateClientSettingsRequest extends UpdatePracticeRecordSettingsRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'fields.*.applies_to' => ['required', 'array', 'min:1'],
            'fields.*.applies_to.*' => ['in:individual,organization'],
            'fields.*.step' => ['nullable', 'integer', 'in:1,2,3'],
        ];
    }
}
