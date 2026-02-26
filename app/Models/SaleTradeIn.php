<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleTradeIn extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'sku',
        'serial_number',
        'brand',
        'series',
        'specs',
        'category_id',
        'trade_in_value',
        'product_id',
    ];

    protected function casts(): array
    {
        return [
            'trade_in_value' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Produk baru yang dibuat dari laptop tukar (setelah release).
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
