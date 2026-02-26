<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Models\PaymentMethod|null $paymentMethod
 */
class CashFlow extends Model
{
    use HasFactory;

    public const TYPE_IN = 'IN';
    public const TYPE_OUT = 'OUT';

    public const REFERENCE_SALE = 'sale';
    public const REFERENCE_SERVICE = 'service';
    public const REFERENCE_EXPENSE = 'expense';
    public const REFERENCE_TRADE_IN = 'trade_in';
    public const REFERENCE_OTHER = 'lainnya';

    protected $fillable = [
        'branch_id',
        'type',
        'amount',
        'description',
        'reference_type',
        'reference_id',
        'expense_category_id',
        'payment_method_id',
        'transaction_date',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    /**
     * Get the user who recorded the transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
