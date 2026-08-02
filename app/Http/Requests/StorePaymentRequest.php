<?php

namespace App\Http\Requests;

use App\Models\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
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
        return [
            'invoice_id' => ['required', 'integer', Rule::exists('invoices', 'id')],
            'payment_method_id' => ['required', 'integer', Rule::exists(PaymentMethod::class, 'id')->where('is_active', true)],
            'amount' => ['required', 'numeric', 'gt:0'], 'received_at' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
