<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_RELEASED = 'released';
    public const STATUS_CANCEL = 'cancel';

    protected $fillable = [
        'invoice_number',
        'branch_id',
        'customer_id',
        'user_id',
        'total',
        'total_paid',
        'discount_amount',
        'tax_amount',
        'description',
        'sale_date',
        'status',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'sale_date' => 'date',
            'released_at' => 'datetime',
        ];
    }

    /**
     * Apakah penjualan sudah lunas (total pembayaran >= total).
     */
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

        return $paid >= $total - 0.02; // toleransi floating point
    }

    /**
     * Get the branch that owns the sale.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the user that created the sale.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the sale details.
     */
    public function saleDetails(): HasMany
    {
        return $this->hasMany(SaleDetail::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    /**
     * Get the trade-ins (laptop yang ditukar) for this sale.
     */
    public function tradeIns(): HasMany
    {
        return $this->hasMany(SaleTradeIn::class, 'sale_id');
    }

    /**
     * Total nilai tukar tambah (untuk perhitungan pembayaran).
     */
    public function getTradeInTotalAttribute(): float
    {
        if (! $this->relationLoaded('tradeIns')) {
            $this->load('tradeIns');
        }

        return (float) $this->tradeIns->sum('trade_in_value');
    }

    /**
     * Total HPP (Harga Pokok Penjualan) dari detail untuk perhitungan laba rugi.
     */
    public function getTotalHppAttribute(): float
    {
        if (! $this->relationLoaded('saleDetails')) {
            $this->load('saleDetails');
        }

        return (float) $this->saleDetails->sum(fn ($d) => (float) $d->quantity * (float) ($d->hpp ?? 0));
    }

    /**
     * Laba kotor = Total penjualan - Total HPP.
     */
    public function getGrossProfitAttribute(): float
    {
        return (float) $this->total - $this->total_hpp;
    }
}
