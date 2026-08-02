<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Models\GeneralSetting;
use App\Models\Matter;
use App\Models\MatterType;
use App\Models\User;
use App\Services\PracticeRecordSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OpenMatterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Matter::class) ?? false;
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
            'deadline_at' => ['nullable', 'date', 'after_or_equal:opened_at'],
            'attorney_ids' => ['nullable', 'array', 'max:20'],
            'attorney_ids.*' => [
                'integer',
                'distinct',
                Rule::exists(User::class, 'id')->where('status', 'active'),
            ],
            'participants' => ['nullable', 'array', 'max:20'],
            'participants.*.role' => ['required_with:participants', 'string', 'max:100'],
            'participants.*.type' => ['required_with:participants', Rule::in(['individual', 'organization'])],
            'participants.*.name' => ['required_with:participants', 'string', 'max:255'],
            'participants.*.contact_person' => ['nullable', 'string', 'max:255'],
            'participants.*.email' => ['nullable', 'email', 'max:255'],
            'participants.*.phone' => ['nullable', 'string', 'max:50'],
            'custom_fields' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('responsible_user_id') || $validator->errors()->has('attorney_ids')) {
                return;
            }

            $attorneyIds = $this->attorneyIds();
            $responsibleAttorneyId = $this->integer('responsible_user_id');
            $selectedIds = array_values(array_unique([...$attorneyIds, $responsibleAttorneyId]));
            $eligibleIds = User::attorneys()
                ->whereIn('id', $selectedIds)
                ->pluck('id')
                ->all();

            if (! in_array($responsibleAttorneyId, $eligibleIds, true)) {
                $validator->errors()->add('responsible_user_id', 'The responsible attorney must hold an active attorney position.');
            }

            foreach ($attorneyIds as $index => $attorneyId) {
                if (! in_array($attorneyId, $eligibleIds, true)) {
                    $validator->errors()->add("attorney_ids.$index", 'Matter participants must hold active attorney positions.');
                }
            }

            app(PracticeRecordSettings::class)->validateValues(
                $validator,
                app(PracticeRecordSettings::class)->matterValues(GeneralSetting::query()->first())['fields'],
                $this->input('custom_fields'),
            );
        }];
    }

    /**
     * @return list<array{role: string, type: 'individual'|'organization', name: string, contact_person?: string, email?: string, phone?: string}>
     */
    public function participants(): array
    {
        $participants = $this->validated('participants', []);

        return is_array($participants) ? array_values($participants) : [];
    }

    /**
     * @return list<int>
     */
    public function attorneyIds(): array
    {
        $attorneyIds = $this->validated('attorney_ids', []);

        if (! is_array($attorneyIds)) {
            return [];
        }

        return array_values(array_map(
            fn (int|string $attorneyId): int => (int) $attorneyId,
            $attorneyIds,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function customFields(): array
    {
        $settings = app(PracticeRecordSettings::class);
        $values = $this->input('custom_fields', []);

        return is_array($values)
            ? $settings->valuesForStorage(
                $settings->matterValues(GeneralSetting::query()->first())['fields'],
                $values,
            )
            : [];
    }
}
