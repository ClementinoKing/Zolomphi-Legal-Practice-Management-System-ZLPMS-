<?php

namespace App\Http\Controllers;

use App\Actions\Payments\CreatePaymentPlan;
use App\Actions\Payments\RecordInvoicePayment;
use App\Http\Requests\StorePaymentPlanRequest;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Invoice;
use App\Models\Matter;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanInstallment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return Inertia::render('payments/index', [
            'payments' => Payment::query()
                ->whereHas('invoice', fn ($invoice) => $invoice
                    ->whereNull('matter_id')
                    ->orWhereHas('matter', fn (Builder $matter): Builder => $matter->whereIn('id', Matter::query()->visibleTo($user)->select('id'))))
                ->with(['invoice.client', 'paymentMethod', 'recorder'])
                ->latest('received_at')
                ->get()
                ->map(fn (Payment $payment): array => [
                    'id' => $payment->id,
                    'number' => $payment->payment_number,
                    'invoice' => $payment->invoice->invoice_number,
                    'client' => $payment->invoice->client->organization_name ?? $payment->invoice->client->full_name,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'method' => $payment->method,
                    'received_at' => $payment->received_at->toDateString(),
                    'reference' => $payment->reference,
                    'recorder' => $payment->recorder->name,
                ])
                ->all(),
            'invoices' => Invoice::query()
                ->where(function ($query) use ($user): void {
                    $query->whereNull('matter_id')
                        ->orWhereHas('matter', fn (Builder $matter): Builder => $matter->whereIn('id', Matter::query()->visibleTo($user)->select('id')));
                })
                ->with('client')
                ->where('balance_due', '>', 0)
                ->orderBy('due_at')
                ->get()
                ->map(fn (Invoice $invoice): array => [
                    'id' => $invoice->id,
                    'number' => $invoice->invoice_number,
                    'client' => $invoice->client->organization_name ?? $invoice->client->full_name,
                    'balance' => $invoice->balance_due,
                    'currency' => $invoice->currency,
                ])
                ->all(),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'plans' => PaymentPlan::query()
                ->whereHas('invoice', fn ($invoice) => $invoice
                    ->whereNull('matter_id')
                    ->orWhereHas('matter', fn (Builder $matter): Builder => $matter->whereIn('id', Matter::query()->visibleTo($user)->select('id'))))
                ->with(['invoice.client', 'installments'])
                ->latest()
                ->get()
                ->map(fn (PaymentPlan $plan): array => [
                    'id' => $plan->id,
                    'invoice' => $plan->invoice->invoice_number,
                    'client' => $plan->invoice->client->organization_name ?? $plan->invoice->client->full_name,
                    'status' => $plan->status,
                    'amount' => $plan->planned_amount,
                    'currency' => $plan->invoice->currency,
                    'installments' => $plan->installments->map(fn (PaymentPlanInstallment $installment): array => [
                        'number' => $installment->installment_number,
                        'amount' => $installment->amount,
                        'paid' => $installment->paid_amount,
                        'due_at' => $installment->due_at->toDateString(),
                        'status' => $installment->status,
                    ])->all(),
                ])
                ->all(),
        ]);
    }

    public function store(StorePaymentRequest $request, RecordInvoicePayment $record): RedirectResponse
    {
        $data = $request->validated();
        $data['method'] = PaymentMethod::query()
            ->whereKey($data['payment_method_id'])
            ->firstOrFail()
            ->name;
        $record->handle($data, $request->user());

        return to_route('payments.index');
    }

    public function storePlan(StorePaymentPlanRequest $request, CreatePaymentPlan $create): RedirectResponse
    {
        $create->handle($request->validated(), $request->user());

        return to_route('payments.index');
    }
}
