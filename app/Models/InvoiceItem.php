<?php

namespace App\Models;

use Database\Factories\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $invoice_id
 * @property int|null $time_entry_id
 * @property int|null $matter_charge_id
 * @property int|null $expense_id
 * @property string $description
 * @property string $quantity
 * @property string $unit_price
 * @property string $tax_rate
 * @property string $tax_amount
 * @property string $amount
 * @property int $sort_order
 */
#[Fillable(['invoice_id', 'time_entry_id', 'matter_charge_id', 'expense_id', 'description', 'quantity', 'unit_price', 'tax_rate', 'tax_amount', 'amount', 'sort_order'])]
class InvoiceItem extends Model
{
    /** @use HasFactory<InvoiceItemFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<TimeEntry, $this>
     */
    public function timeEntry(): BelongsTo
    {
        return $this->belongsTo(TimeEntry::class);
    }

    /**
     * @return BelongsTo<MatterCharge, $this>
     */
    public function matterCharge(): BelongsTo
    {
        return $this->belongsTo(MatterCharge::class);
    }

    /**
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }
}
