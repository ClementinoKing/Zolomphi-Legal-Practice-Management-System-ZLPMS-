<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('payments.view');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->viewAny($user) && $user->can('view', $payment->invoice);
    }

    public function record(User $user): bool
    {
        return $user->hasPermission('payments.record');
    }

    public function create(User $user): bool
    {
        return $this->record($user);
    }

    public function managePlan(User $user): bool
    {
        return $user->hasPermission('payment-plans.manage');
    }
}
