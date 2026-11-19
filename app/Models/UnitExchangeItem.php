<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitExchangeItem extends Model
{
    protected $fillable = [
        'unit_exchange_id',
        'sale_detail_id',
        'product_id',
        'old_unit_id',
        'new_unit_id',
        'old_serial',
        'new_serial',
        'unit_price',
        'price_difference',
        'payment_method_id',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'price_difference' => 'decimal:2',
        ];
    }

    public function exchange(): BelongsTo
    {
        return $this->belongsTo(UnitExchange::class, 'unit_exchange_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function oldUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'old_unit_id');
    }

    public function newUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'new_unit_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
