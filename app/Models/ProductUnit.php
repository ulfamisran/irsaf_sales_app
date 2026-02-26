<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUnit extends Model
{
    use HasFactory;

    public const STATUS_IN_STOCK = 'in_stock';
    /**
     * Reserved for an OPEN (unpaid) sale.
     */
    public const STATUS_KEEP = 'keep';
    public const STATUS_SOLD = 'sold';

    protected $fillable = [
        'product_id',
        'serial_number',
        'location_type',
        'location_id',
        'status',
        'received_date',
        'sold_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'received_date' => 'date',
            'sold_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the current location (warehouse or branch).
     */
    public function location()
    {
        return $this->location_type === Stock::LOCATION_WAREHOUSE
            ? $this->belongsTo(Warehouse::class, 'location_id')
            : $this->belongsTo(Branch::class, 'location_id');
    }
}

