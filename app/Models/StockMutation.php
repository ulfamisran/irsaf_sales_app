<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMutation extends Model
{
    use HasFactory;

    protected $table = 'stock_mutations';

    protected $fillable = [
        'product_id',
        'from_location_type',
        'from_location_id',
        'to_location_type',
        'to_location_id',
        'quantity',
        'mutation_date',
        'notes',
        'serial_numbers',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'mutation_date' => 'date',
        ];
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
}
