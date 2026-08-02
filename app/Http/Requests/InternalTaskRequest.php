<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InternalTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $assigneeRules = [
            'array',
            'max:20',
            $this->isMethod('post') ? 'min:1' : 'sometimes',
        ];

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'status' => ['sometimes', Rule::in(['pending', 'in_progress', 'completed', 'cancelled'])],
            'due_at' => ['nullable', 'date'],
            'assignee_ids' => $assigneeRules,
            'assignee_ids.*' => [
                'integer',
                Rule::exists(User::class, 'id')->where('status', 'active'),
                Rule::notIn([$this->user()?->id]),
            ],
        ];
    }
}
