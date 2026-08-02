<?php

namespace App\Models;

use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $invoice_number
 * @property int $client_id
 * @property int|null $matter_id
 * @property int|null $created_by_user_id
 * @property string $status
 * @property string $currency
 * @property Carbon|null $issued_at
 * @property Carbon|null $due_at
 * @property string $subtotal
 * @property string $tax_amount
 * @property string $discount_amount
 * @property string $total_amount
 * @property string $paid_amount
 * @property string $balance_due
 * @property string|null $notes
 * @property string|null $terms
 * @property Carbon|null $sent_at
 * @property Carbon|null $paid_at
 * @property-read string $amount
 * @property-read int $total
 * @property-read Client $client
 * @property-read Matter|null $matter
 * @property-read User|null $creator
 */
#[Fillable(['invoice_number', 'client_id', 'matter_id', 'created_by_user_id', 'status', 'currency', 'issued_at', 'due_at', 'subtotal', 'tax_amount', 'discount_amount', 'total_amount', 'paid_amount', 'balance_due', 'notes', 'terms', 'paid_at'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Matter, $this>
     */
    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('received_at');
    }

    /**
     * @return HasOne<PaymentPlan, $this>
     */
    public function paymentPlan(): HasOne
    {
        return $this->hasOne(PaymentPlan::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'due_at' => 'date',
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_due' => 'decimal:2',
        ];
    }
}
