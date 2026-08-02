<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $invoice_id
 * @property int|null $payment_plan_id
 * @property int $recorded_by_user_id
 * @property int|null $payment_method_id
 * @property string $payment_number
 * @property string $method
 * @property string $currency
 * @property string $amount
 * @property Carbon $received_at
 * @property string|null $reference
 * @property string|null $notes
 * @property-read Invoice $invoice
 * @property-read PaymentMethod|null $paymentMethod
 * @property-read PaymentPlan|null $plan
 * @property-read User $recorder
 */
#[Fillable(['invoice_id', 'payment_plan_id', 'recorded_by_user_id', 'payment_method_id', 'payment_number', 'method', 'currency', 'amount', 'received_at', 'reference', 'notes'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<PaymentMethod, $this> */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /** @return BelongsTo<PaymentPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PaymentPlan::class, 'payment_plan_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /** @return HasMany<PaymentInstallmentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentInstallmentAllocation::class);
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'received_at' => 'date'];
    }
}
