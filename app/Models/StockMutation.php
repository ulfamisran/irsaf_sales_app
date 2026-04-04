<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMutation extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'stock_mutations';

    protected $fillable = [
        'invoice_number',
        'product_id',
        'from_location_type',
        'from_location_id',
        'to_location_type',
        'to_location_id',
        'quantity',
        'biaya_distribusi_per_unit',
        'distribution_payment_method_id',
        'mutation_date',
        'notes',
        'serial_numbers',
        'user_id',
        'status',
        'cancel_date',
        'cancel_user_id',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'biaya_distribusi_per_unit' => 'decimal:2',
            'mutation_date' => 'date',
            'cancel_date' => 'date',
        ];
    }

    public function isCancelled(): bool
    {
        return ($this->status ?? self::STATUS_ACTIVE) === self::STATUS_CANCELLED;
    }

    public function cancelUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancel_user_id');
    }

    /**
     * Get the product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user who created the mutation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function distributionPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'distribution_payment_method_id');
    }

    public function purchase(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Purchase::class);
    }
}
