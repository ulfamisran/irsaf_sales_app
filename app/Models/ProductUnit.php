<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    /**
     * Unit tidak dihitung sebagai stok tersedia (mis. setelah pembelian dibatalkan / belum lolos cek).
     */
    public const STATUS_NOT_IN_STOCK = 'not_in_stock';
    public const STATUS_RETURE = 'reture';
    public const STATUS_PENDING = 'pending';

    public const CHECK_UNCHECKED = 'unchecked';
    public const CHECK_CHECKED = 'checked';

    protected $fillable = [
        'product_id',
        'purchase_id',
        'purchase_detail_id',
        'user_id',
        'harga_hpp',
        'harga_jual',
        'serial_number',
        'location_type',
        'location_id',
        'status',
        'check_status',
        'checked_at',
        'checked_by',
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
            'checked_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function purchaseDetail(): BelongsTo
    {
        return $this->belongsTo(PurchaseDetail::class);
    }

    public function checkedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
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

    public function changeLogs(): HasMany
    {
        return $this->hasMany(ProductUnitChangeLog::class)->orderByDesc('changed_at')->orderByDesc('id');
    }
}

