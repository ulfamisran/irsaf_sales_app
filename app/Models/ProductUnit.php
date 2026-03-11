<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductUnit extends Model
{
    use HasFactory;

    public const STATUS_IN_STOCK = 'in_stock';
    /**
     * Reserved for an OPEN (unpaid) sale.
     */
    public const STATUS_KEEP = 'keep';
    /**
     * Reserved for damaged goods recording.
     */
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_SOLD = 'sold';
    public const STATUS_IN_RENT = 'in_rent';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_CANCEL = 'cancel';

    protected $fillable = [
        'product_id',
        'user_id',
        'harga_hpp',
        'harga_jual',
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
            'harga_hpp' => 'decimal:2',
            'harga_jual' => 'decimal:2',
            'received_date' => 'date',
            'sold_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'location_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'location_id');
    }

    public function damagedGood(): HasOne
    {
        return $this->hasOne(DamagedGood::class)->whereNull('reactivated_at');
    }
}

