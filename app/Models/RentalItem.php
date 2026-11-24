<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalItem extends Model
{
    use HasFactory;

    public const CONDITION_BAIK = 'baik';

    public const CONDITION_BERMASALAH = 'bermasalah';

    protected $fillable = [
        'rental_id',
        'product_id',
        'serial_number',
        'rental_price',
        'days',
        'total',
        'is_returned',
        'returned_at',
        'return_condition',
        'return_notes',
    ];

    protected function casts(): array
    {
        return [
            'rental_price' => 'decimal:2',
            'total' => 'decimal:2',
            'is_returned' => 'boolean',
            'returned_at' => 'datetime',
        ];
    }

    public static function conditionLabels(): array
    {
        return [
            self::CONDITION_BAIK => __('Baik'),
            self::CONDITION_BERMASALAH => __('Bermasalah'),
        ];
    }

    public function getReturnConditionLabelAttribute(): string
    {
        return self::conditionLabels()[$this->return_condition] ?? ($this->return_condition ?: '—');
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
