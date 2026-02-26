<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    use HasFactory;

    public const LOCATION_WAREHOUSE = 'warehouse';
    public const LOCATION_BRANCH = 'branch';

    protected $fillable = [
        'product_id',
        'location_type',
        'location_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
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
     * Get the location (warehouse or branch).
     */
    public function location()
    {
        return $this->location_type === self::LOCATION_WAREHOUSE
            ? $this->belongsTo(Warehouse::class, 'location_id')
            : $this->belongsTo(Branch::class, 'location_id');
    }
}
