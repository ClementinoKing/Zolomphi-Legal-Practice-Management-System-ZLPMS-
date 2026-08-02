<?php

namespace App\Http\Requests;

use App\Models\Matter;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartTimeTrackerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $matter = Matter::query()->find($this->integer('matter_id'));

        $user = $this->user();

        return $matter !== null
            && $user instanceof User
            && $user->hasPermission('time-entries.create')
            && $user->can('view', $matter);
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
