<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $invoice = Invoice::query()->find($this->integer('invoice_id'));

        $user = $this->user();

        return $invoice !== null
            && $user instanceof User
            && $user->can('managePlan', Payment::class)
            && $user->can('view', $invoice);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'integer', Rule::exists('invoices', 'id')],
            'installment_count' => ['required', 'integer', 'min:2', 'max:60'],
            'first_due_at' => ['required', 'date', 'after_or_equal:today'],
        ];
    }
}
