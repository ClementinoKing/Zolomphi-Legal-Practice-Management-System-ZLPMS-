<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Models\MatterType;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenMatterRequest extends FormRequest
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
            'client_id' => [
                'required',
                'integer',
                Rule::exists(Client::class, 'id')->where('status', 'active'),
            ],
            'matter_type_id' => [
                'required',
                'integer',
                Rule::exists(MatterType::class, 'id')->where('is_active', true),
            ],
            'responsible_user_id' => [
                'required',
                'integer',
                Rule::exists(User::class, 'id')->where('status', 'active'),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['open', 'pending', 'on_hold'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'billing_type' => ['required', Rule::in(['hourly', 'fixed_fee', 'contingency'])],
            'default_hourly_rate' => ['nullable', 'numeric', 'min:0', 'required_if:billing_type,hourly'],
            'budget_amount' => ['nullable', 'numeric', 'min:0'],
            'opened_at' => ['required', 'date'],
            'participants' => ['nullable', 'array', 'max:20'],
            'participants.*.role' => ['required_with:participants', 'string', 'max:100'],
            'participants.*.type' => ['required_with:participants', Rule::in(['individual', 'organization'])],
            'participants.*.name' => ['required_with:participants', 'string', 'max:255'],
            'participants.*.contact_person' => ['nullable', 'string', 'max:255'],
            'participants.*.email' => ['nullable', 'email', 'max:255'],
            'participants.*.phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return list<array{role: string, type: 'individual'|'organization', name: string, contact_person?: string, email?: string, phone?: string}>
     */
    public function participants(): array
    {
        $participants = $this->validated('participants', []);

        return is_array($participants) ? array_values($participants) : [];
    }
}
