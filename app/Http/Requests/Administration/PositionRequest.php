<?php

namespace App\Http\Requests\Administration;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-administration') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $position = $this->route('position');

        return [
            'department_id' => [
                'required',
                'integer',
                Rule::exists(Department::class, 'id')->where('is_active', true),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Position::class, 'name')
                    ->where('department_id', $this->integer('department_id'))
                    ->ignore($position),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
            'is_attorney' => ['required', 'boolean'],
        ];
    }
}
