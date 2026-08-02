<?php

namespace App\Http\Requests\Administration;

use App\Models\Position;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAdministrativeUserRequest extends FormRequest
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
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:50'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'position_id' => [
                'nullable',
                'integer',
                Rule::exists(Position::class, 'id')->whereNull('deleted_at'),
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
