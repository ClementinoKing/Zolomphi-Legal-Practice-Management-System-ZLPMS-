<?php

namespace App\Http\Requests;

use App\Models\BillingItem;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MatterWorkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('access-administration') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return match ($this->route('work')) {
            'tasks' => ['title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'], 'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])], 'due_at' => ['nullable', 'date'], 'assignee_ids' => ['nullable', 'array', 'max:20'], 'assignee_ids.*' => ['integer', Rule::exists(User::class, 'id')->where('status', 'active')]],
            'time-entries' => ['entry_date' => ['required', 'date'], 'description' => ['required', 'string', 'max:5000'], 'hours' => ['required', 'numeric', 'min:0.25', 'max:24', 'multiple_of:0.25'], 'hourly_rate' => ['nullable', 'numeric', 'min:0'], 'is_billable' => ['required', 'boolean'], 'status' => ['required', Rule::in(['draft', 'submitted', 'approved'])]],
            'documents' => ['document' => [$this->isMethod('POST') ? 'required' : 'nullable', 'file', 'max:25600', 'mimes:pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png'], 'name' => ['nullable', 'string', 'max:255'], 'category' => ['required', 'string', 'max:100'], 'visibility' => ['required', Rule::in(['internal', 'client'])]],
            'notes' => ['body' => ['required', 'string', 'max:10000']],
            'emails' => ['to' => ['required', 'array', 'min:1', 'max:20'], 'to.*' => ['email', 'max:255'], 'cc' => ['nullable', 'array', 'max:20'], 'cc.*' => ['email', 'max:255'], 'subject' => ['required', 'string', 'max:255'], 'body' => ['required', 'string', 'max:20000'], 'document_ids' => ['nullable', 'array', 'max:10'], 'document_ids.*' => ['integer']],
            'calendar-events' => ['title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'], 'type' => ['required', Rule::in(['meeting', 'hearing', 'deadline', 'reminder'])], 'location' => ['nullable', 'string', 'max:255'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after_or_equal:starts_at'], 'is_all_day' => ['required', 'boolean'], 'attendee_ids' => ['nullable', 'array', 'max:20'], 'attendee_ids.*' => ['integer', Rule::exists(User::class, 'id')->where('status', 'active')]],
            'expenses' => ['expense_category_id' => ['required', 'integer', Rule::exists(ExpenseCategory::class, 'id')->where('is_active', true)], 'vendor' => ['nullable', 'string', 'max:255'], 'amount' => ['required', 'numeric', 'gt:0'], 'incurred_at' => ['required', 'date'], 'is_billable' => ['required', 'boolean'], 'description' => ['nullable', 'string', 'max:5000'], 'receipt' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png']],
            'flat-fees' => ['billing_item_id' => ['nullable', 'integer', Rule::exists(BillingItem::class, 'id')->where('is_active', true)], 'description' => ['required', 'string', 'max:255'], 'quantity' => ['required', 'numeric', 'min:0.01'], 'unit_price' => ['required', 'numeric', 'min:0'], 'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'], 'charged_at' => ['required', 'date'], 'status' => ['required', Rule::in(['draft', 'billable'])]],
            default => [],
        };
    }

    protected function prepareForValidation(): void
    {
        foreach (['to', 'cc'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => array_values(array_filter(array_map('trim', explode(',', $this->string($field)->toString()))))]);
            }
        }

        foreach (['is_billable', 'is_all_day'] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }
    }
}
