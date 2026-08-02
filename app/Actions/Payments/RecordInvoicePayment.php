<?php

namespace App\Actions\Payments;

use App\Actions\Audit\RecordAuditEvent;
use App\Actions\Notifications\SendPracticeAlert;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordInvoicePayment
{
    public function __construct(
        private readonly GeneratePaymentNumber $numbers,
        private readonly RecordAuditEvent $audit,
        private readonly SendPracticeAlert $alerts,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, User $user): Payment
    {
        return DB::transaction(function () use ($data, $user): Payment {
            $invoice = Invoice::query()
                ->with(['creator:id,name', 'matter.responsibleUser:id,name'])
                ->whereKey($data['invoice_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $amount = round((float) $data['amount'], 2);
            if ($amount > (float) $invoice->balance_due) {
                throw ValidationException::withMessages(['amount' => 'Payment cannot exceed the outstanding balance.']);
            }
            $invoice->update([
                'status' => 'issued',
                'issued_at' => $invoice->issued_at ?? today(),
            ]);
            $plan = $invoice->paymentPlan()->where('status', 'active')->lockForUpdate()->first();
            $payment = Payment::query()->create([...$data, 'payment_plan_id' => $plan?->id, 'recorded_by_user_id' => $user->id, 'payment_number' => $this->numbers->handle(), 'currency' => $invoice->currency, 'method' => $data['method']]);
            $remaining = $amount;
            if ($plan !== null) {
                foreach ($plan->installments()->lockForUpdate()->get() as $installment) {
                    $due = round((float) $installment->amount - (float) $installment->paid_amount, 2);
                    if ($remaining <= 0 || $due <= 0) {
                        continue;
                    }
                    $allocated = min($remaining, $due);
                    $payment->allocations()->create(['payment_plan_installment_id' => $installment->id, 'amount' => $allocated]);
                    $paid = round((float) $installment->paid_amount + $allocated, 2);
                    $installment->update(['paid_amount' => $paid, 'status' => $paid >= (float) $installment->amount ? 'paid' : 'partial']);
                    $remaining = round($remaining - $allocated, 2);
                }
            }
            $paid = round((float) $invoice->paid_amount + $amount, 2);
            $balance = round((float) $invoice->total_amount - $paid, 2);
            $invoice->update(['paid_amount' => $paid, 'balance_due' => $balance, 'status' => $balance === 0.0 ? 'paid' : 'partially_paid', 'paid_at' => $balance === 0.0 ? now() : null]);
            if ($plan !== null && ! $plan->installments()->where('status', '!=', 'paid')->exists()) {
                $plan->update(['status' => 'completed']);
            }
            $this->audit->handle(
                user: $user,
                event: 'payment.recorded',
                description: 'Recorded payment '.$payment->payment_number.' for '.$invoice->invoice_number.'.',
                subject: $payment,
                clientId: $invoice->client_id,
                matterId: $invoice->matter_id,
                properties: ['invoice_number' => $invoice->invoice_number, 'amount' => $payment->amount],
            );
            $recipientIds = array_filter([$invoice->created_by_user_id, $invoice->matter?->responsible_user_id]);
            $this->alerts->handle(
                User::query()->whereKey($recipientIds)->get(),
                $user,
                [
                    'title' => 'Payment recorded',
                    'body' => $payment->payment_number.' was recorded against '.$invoice->invoice_number.'.',
                    'url' => route('payments.index'),
                    'type' => 'payment',
                ],
            );

            return $payment;
        });
    }
}
