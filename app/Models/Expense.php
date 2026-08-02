<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string|null $receipt_path
 * @property string|null $receipt_disk
 * @property int $id
 * @property int|null $matter_id
 * @property string $expense_number
 * @property string $category
 * @property string|null $vendor
 * @property string $currency
 * @property string $amount
 * @property Carbon $incurred_at
 * @property bool $is_billable
 * @property string $status
 * @property string|null $description
 */
#[Fillable(['matter_id', 'expense_category_id', 'recorded_by_user_id', 'expense_number', 'category', 'vendor', 'currency', 'amount', 'incurred_at', 'is_billable', 'status', 'description', 'receipt_path', 'receipt_disk'])]
class Expense extends Model
{
    /**
     * @return BelongsTo<Matter, $this>
     */
    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function categoryRecord(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
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
        return ['amount' => 'decimal:2', 'incurred_at' => 'date', 'is_billable' => 'boolean'];
    }
}
