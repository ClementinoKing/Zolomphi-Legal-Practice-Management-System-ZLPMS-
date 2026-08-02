<?php

namespace App\Actions\Payments;

use App\Actions\Audit\RecordAuditEvent;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePaymentPlan
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, User $user): PaymentPlan
    {
        return DB::transaction(function () use ($data, $user): PaymentPlan {
            if (! $user->can('managePlan', Payment::class)) {
                throw new AuthorizationException;
            }

            $invoice = Invoice::query()
                ->whereKey($data['invoice_id'])
                ->lockForUpdate()
                ->firstOrFail();
            if (! $user->can('view', $invoice)) {
                throw new AuthorizationException;
            }
            if ((float) $invoice->balance_due <= 0 || $invoice->paymentPlan()->where('status', 'active')->exists()) {
                throw ValidationException::withMessages(['invoice_id' => 'This invoice is not eligible for a new payment plan.']);
            }
            $count = (int) $data['installment_count'];
            $amount = round((float) $invoice->balance_due, 2);
            $each = floor(($amount / $count) * 100) / 100;
            $plan = PaymentPlan::query()->create(['invoice_id' => $invoice->id, 'created_by_user_id' => $user->id, 'status' => 'active', 'installment_count' => $count, 'planned_amount' => $amount, 'first_due_at' => $data['first_due_at']]);
            foreach (range(1, $count) as $number) {
                $plan->installments()->create(['installment_number' => $number, 'amount' => $number === $count ? round($amount - ($each * ($count - 1)), 2) : $each, 'due_at' => Carbon::parse($data['first_due_at'])->addMonthsNoOverflow($number - 1)]);
            }
            $invoice->update([
                'status' => (float) $invoice->paid_amount > 0 ? 'partially_paid' : 'issued',
                'issued_at' => $invoice->issued_at ?? today(),
            ]);
            $this->audit->handle(
                user: $user,
                event: 'payment_plan.created',
                description: 'Created a '.$count.'-installment plan for '.$invoice->invoice_number.'.',
                subject: $plan,
                clientId: $invoice->client_id,
                matterId: $invoice->matter_id,
                properties: ['invoice_number' => $invoice->invoice_number, 'installment_count' => $count],
            );

            return $plan;
        });
    }
}
