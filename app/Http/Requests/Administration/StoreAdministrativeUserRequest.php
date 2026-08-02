<?php

namespace App\Http\Requests\Administration;

use App\Models\Position;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAdministrativeUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('users.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:50'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
            'position_id' => [
                'nullable',
                'integer',
                Rule::exists(Position::class, 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', Rule::exists('roles', 'id')],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->filled('position_id') || $validator->errors()->has('position_id')) {
                    return;
                }

                $position = Position::query()->find($this->integer('position_id'));

                if ($position === null || $position->department_id !== $this->integer('department_id')) {
                    $validator->errors()->add('position_id', 'The selected position must belong to the selected department.');
                }
            },
        ];
    }
}
