<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReceivingLog extends Model
{
    public const RESULT_BAIK = 'baik';
    public const RESULT_RETURE = 'reture';
    public const RESULT_PENDING = 'pending';

    protected $fillable = [
        'purchase_id',
        'purchase_detail_id',
        'product_unit_id',
        'result',
        'previous_result',
        'qty',
        'checked_by',
        'checked_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'checked_at' => 'datetime',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function purchaseDetail(): BelongsTo
    {
        return $this->belongsTo(PurchaseDetail::class);
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function resultLabel(): string
    {
        return match ($this->result) {
            self::RESULT_BAIK => __('Baik'),
            self::RESULT_RETURE => __('Reture'),
            self::RESULT_PENDING => __('Pending'),
            default => $this->result,
        };
    }

    public function isSerialCheck(): bool
    {
        return (int) $this->product_unit_id > 0;
    }
}
