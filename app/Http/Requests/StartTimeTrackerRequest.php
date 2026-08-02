<?php

namespace App\Http\Requests;

use App\Models\Matter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartTimeTrackerRequest extends FormRequest
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
            'matter_id' => [
                'required',
                'integer',
                Rule::exists(Matter::class, 'id')->whereNull('deleted_at'),
            ],
        ];
    }
}
