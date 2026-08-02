<?php

namespace App\Models;

use Database\Factories\PaymentPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $invoice_id
 * @property int $created_by_user_id
 * @property string $status
 * @property int $installment_count
 * @property string $planned_amount
 * @property Carbon $first_due_at
 * @property-read Invoice $invoice
 * @property-read Collection<int, PaymentPlanInstallment> $installments
 */
#[Fillable(['invoice_id', 'created_by_user_id', 'status', 'installment_count', 'planned_amount', 'first_due_at'])]
class PaymentPlan extends Model
{
    /** @use HasFactory<PaymentPlanFactory> */
    use HasFactory;

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return HasMany<PaymentPlanInstallment, $this> */
    public function installments(): HasMany
    {
        return $this->hasMany(PaymentPlanInstallment::class)->orderBy('installment_number');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    protected function casts(): array
    {
        return ['planned_amount' => 'decimal:2', 'first_due_at' => 'date'];
    }
}
