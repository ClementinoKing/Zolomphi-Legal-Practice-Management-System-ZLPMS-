<?php

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

function paymentAdmin(): User
{
    $permission = Permission::query()->create(['key' => 'administration.access', 'name' => 'Access administration', 'group' => 'Administration']);
    $role = Role::query()->create(['name' => 'Payments Admin']);
    $role->assignedPermissions()->attach($permission);
    $user = User::factory()->create();
    $user->roles()->attach($role);

    return grantAllPracticePermissions($user);
}
function paymentInvoice(User $user): Invoice
{
    return Invoice::factory()->create(['created_by_user_id' => $user->id, 'status' => 'draft', 'currency' => 'MWK', 'total_amount' => 1000, 'balance_due' => 1000, 'paid_amount' => 0]);
}

test('recording payments updates invoice totals and prevents overpayment', function () {
    $user = paymentAdmin();
    $invoice = paymentInvoice($user);
    $method = PaymentMethod::query()->create(['name' => 'Bank transfer', 'code' => 'bank-transfer', 'is_active' => true]);
    $this->actingAs($user)->post(route('payments.store'), ['invoice_id' => $invoice->id, 'payment_method_id' => $method->id, 'amount' => 400, 'received_at' => '2026-08-01'])->assertRedirect(route('payments.index', absolute: false));
    expect(Payment::query()->sole()->payment_number)->toBe('PAY-000001');
    expect($invoice->refresh()->paid_amount)->toBe('400.00');
    expect($invoice->balance_due)->toBe('600.00');
    expect($invoice->status)->toBe('partially_paid');
    expect($invoice->issued_at)->not->toBeNull();
    $this->assertDatabaseHas('activity_logs', ['event' => 'payment.recorded']);
    $this->post(route('payments.store'), ['invoice_id' => $invoice->id, 'payment_method_id' => $method->id, 'amount' => 601, 'received_at' => '2026-08-02'])->assertSessionHasErrors('amount');
    expect(Payment::query()->count())->toBe(1);
    $this->post(route('payments.store'), ['invoice_id' => $invoice->id, 'payment_method_id' => $method->id, 'amount' => 600, 'received_at' => '2026-08-02'])->assertRedirect();
    expect($invoice->refresh()->status)->toBe('paid');
    expect($invoice->balance_due)->toBe('0.00');
    expect($invoice->paid_at)->not->toBeNull();
});

test('staged plans create equal monthly installments and allocate payments', function () {
    $user = paymentAdmin();
    $invoice = paymentInvoice($user);
    $method = PaymentMethod::query()->create(['name' => 'Cash', 'code' => 'cash', 'is_active' => true]);
    $this->actingAs($user)->post(route('payments.plans.store'), ['invoice_id' => $invoice->id, 'installment_count' => 3, 'first_due_at' => '2026-08-15'])->assertRedirect();
    $plan = $invoice->refresh()->paymentPlan()->with('installments')->firstOrFail();
    expect($plan->installments->pluck('amount')->all())->toBe(['333.33', '333.33', '333.34']);
    expect($invoice->status)->toBe('issued');
    $this->assertDatabaseHas('activity_logs', ['event' => 'payment_plan.created']);
    $this->post(route('payments.store'), ['invoice_id' => $invoice->id, 'payment_method_id' => $method->id, 'amount' => 500, 'received_at' => '2026-08-16'])->assertRedirect();
    $plan->refresh();
    expect($plan->installments()->first()->paid_amount)->toBe('333.33');
    expect($plan->installments()->skip(1)->first()->paid_amount)->toBe('166.67');
});
