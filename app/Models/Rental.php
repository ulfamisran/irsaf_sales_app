<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rental extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_RELEASED = 'released';
    public const STATUS_CANCEL = 'cancel';

    public const RETURN_BELUM = 'belum_kembali';
    public const RETURN_SUDAH = 'sudah_kembali';

    public const PAYMENT_BELUM_LUNAS = 'belum_lunas';
    public const PAYMENT_LUNAS = 'lunas';

    protected $fillable = [
        'invoice_number',
        'branch_id',
        'warehouse_id',
        'customer_id',
        'user_id',
        'pickup_date',
        'return_date',
        'total_days',
        'subtotal',
        'tax_amount',
        'penalty_amount',
        'total',
        'total_paid',
        'payment_status',
        'return_status',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'pickup_date' => 'date',
            'return_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'penalty_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'total_paid' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RentalItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RentalPayment::class);
    }

    public function isPaidOff(): bool
    {
        $total = (float) $this->total;
        if ($total <= 0) {
            return true;
        }
        $paid = (float) $this->total_paid;
        if ($paid <= 0 && $this->relationLoaded('payments')) {
            $paid = (float) $this->payments->sum('amount');
        }

        return $paid >= $total - 0.02;
    }
}
