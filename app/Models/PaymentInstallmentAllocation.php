<?php

namespace App\Models;

use Database\Factories\PaymentInstallmentAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payment_id', 'payment_plan_installment_id', 'amount'])]
class PaymentInstallmentAllocation extends Model
{
    /** @use HasFactory<PaymentInstallmentAllocationFactory> */
    use HasFactory;

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<PaymentPlanInstallment, $this> */
    public function installment(): BelongsTo
    {
        return $this->belongsTo(PaymentPlanInstallment::class, 'payment_plan_installment_id');
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }
}
