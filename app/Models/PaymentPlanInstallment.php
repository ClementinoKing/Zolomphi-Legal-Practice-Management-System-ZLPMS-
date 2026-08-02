<?php

namespace App\Models;

use Database\Factories\PaymentPlanInstallmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $payment_plan_id
 * @property int $installment_number
 * @property string $amount
 * @property string $paid_amount
 * @property Carbon $due_at
 * @property string $status
 */
#[Fillable(['payment_plan_id', 'installment_number', 'amount', 'paid_amount', 'due_at', 'status'])]
class PaymentPlanInstallment extends Model
{
    /** @use HasFactory<PaymentPlanInstallmentFactory> */
    use HasFactory;

    /** @return BelongsTo<PaymentPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PaymentPlan::class, 'payment_plan_id');
    }

    /** @return HasMany<PaymentInstallmentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentInstallmentAllocation::class);
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'due_at' => 'date'];
    }
}
