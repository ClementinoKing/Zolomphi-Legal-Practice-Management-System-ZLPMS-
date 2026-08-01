<?php

namespace App\Http\Requests\Administration;

use App\Models\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PermissionRequest extends FormRequest
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
        /** @var Permission|null $permission */
        $permission = $this->route('permission');

        return [
            'key' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9.-]*$/', Rule::unique(Permission::class, 'key')->ignore($permission)],
            'name' => ['required', 'string', 'max:100'],
            'group' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
