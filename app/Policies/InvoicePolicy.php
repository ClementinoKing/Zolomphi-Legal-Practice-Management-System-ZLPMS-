<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('invoices.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $invoice->matter_id === null
            || $user->hasPermission('matters.view.all')
            || ($invoice->matter !== null && $user->can('view', $invoice->matter));
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('invoices.manage');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.manage') && $this->view($user, $invoice);
    }

    public function configure(User $user): bool
    {
        return $user->hasPermission('invoices.configure');
    }
}
