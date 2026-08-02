<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $matter_id
 * @property int|null $billing_item_id
 * @property int|null $created_by_user_id
 * @property string $description
 * @property string $quantity
 * @property string $unit_price
 * @property string $tax_rate
 * @property string $amount
 * @property string $status
 * @property Carbon $charged_at
 */
#[Fillable(['matter_id', 'billing_item_id', 'created_by_user_id', 'description', 'quantity', 'unit_price', 'tax_rate', 'amount', 'status', 'charged_at'])]
class MatterCharge extends Model
{
    /**
     * @return BelongsTo<Matter, $this>
     */
    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    /**
     * @return BelongsTo<BillingItem, $this>
     */
    public function billingItem(): BelongsTo
    {
        return $this->belongsTo(BillingItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasOne<InvoiceItem, $this>
     */
    public function invoiceItem(): HasOne
    {
        return $this->hasOne(InvoiceItem::class);
    }

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'tax_rate' => 'decimal:2', 'amount' => 'decimal:2', 'charged_at' => 'date'];
    }
}
