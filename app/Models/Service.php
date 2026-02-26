<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCEL = 'cancel';

    public const PICKUP_BELUM = 'belum_diambil';
    public const PICKUP_SUDAH = 'sudah_diambil';

    public const PAYMENT_BELUM_LUNAS = 'belum_lunas';
    public const PAYMENT_LUNAS = 'lunas';

    protected $fillable = [
        'invoice_number',
        'branch_id',
        'user_id',
        'customer_id',
        'laptop_type',
        'laptop_detail',
        'damage_description',
        'service_cost',
        'service_price',
        'total_paid',
        'entry_date',
        'exit_date',
        'pickup_status',
        'payment_status',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'service_cost' => 'decimal:2',
            'service_price' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'entry_date' => 'date',
            'exit_date' => 'date',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ServicePayment::class);
    }

    public function isPaidOff(): bool
    {
        $total = (float) $this->service_price;
        if ($total <= 0) {
            return true;
        }
        $paid = (float) $this->total_paid;
        if ($paid <= 0 && $this->relationLoaded('payments')) {
            $paid = (float) $this->payments->sum('amount');
        }
        return $paid >= $total - 0.02;
    }

    /**
     * Laba service = harga service - biaya service.
     */
    public function getGrossProfitAttribute(): float
    {
        return (float) $this->service_price - (float) $this->service_cost;
    }
}
