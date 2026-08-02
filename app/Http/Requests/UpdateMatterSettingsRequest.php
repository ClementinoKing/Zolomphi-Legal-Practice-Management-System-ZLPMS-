<?php

namespace App\Http\Requests;

class UpdateMatterSettingsRequest extends UpdatePracticeRecordSettingsRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'fields.*.step' => ['required', 'integer', 'between:1,3']];
    }
}
